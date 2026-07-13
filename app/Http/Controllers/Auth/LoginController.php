<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\SelectedBranchSession;
use App\Support\SelectedCompanySession;
use App\Support\TenantContext;
use App\Support\UserSessionManager;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Auth::user()->dashboardUrl());
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $email = $request->string('email')->trim()->toString();
        $password = $request->string('password')->toString();
        $remember = $request->boolean('remember');

        $user = User::query()
            ->withoutGlobalScope('tenant')
            ->where('email', $email)
            ->first();

        if ($user === null) {
            return $this->loginErrorResponse($request, 'Email does not exist.');
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            return $this->loginErrorResponse($request, 'Incorrect password.');
        }

        if (! $user->canLoginWithCurrentBranchAssignment()) {
            return $this->blockedLoginResponse(
                $request,
                'No branch is assigned to your account. Please contact your Super Admin.',
            );
        }

        UserSessionManager::invalidateOtherSessions($user, $request->session()->getId());
        UserSessionManager::rotateRememberToken($user);

        Auth::login($user, $remember);

        $request->session()->regenerate();

        UserSessionManager::invalidateOtherSessions($user, $request->session()->getId());

        $user->loadMissing(['userRole', 'tenant']);
        TenantContext::setTenantId($user->effectiveTenantId());

        if ($request->wantsJson()) {
            return response()->json([
                'user' => new UserResource($user),
                'redirect' => $user->dashboardUrl(),
            ]);
        }

        return redirect()->to($user->dashboardUrl());
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        Auth::logout();
        SelectedCompanySession::forget();
        SelectedBranchSession::forget();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        TenantContext::setTenantId(null);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Logged out.',
                'redirect' => route('app.login', absolute: false),
            ]);
        }

        return redirect()
            ->route('app.login', ['logged_out' => 1])
            ->with('status', 'You have been signed out.');
    }

    private function blockedLoginResponse(LoginRequest $request, string $message): RedirectResponse|JsonResponse
    {
        return $this->loginErrorResponse($request, $message);
    }

    private function loginErrorResponse(LoginRequest $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => ['email' => [$message]],
            ], 422);
        }

        return redirect()
            ->route('app.login')
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => $message]);
    }
}
