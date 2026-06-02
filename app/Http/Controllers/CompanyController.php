<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Support\CompanyProvisioner;
use App\Support\SelectedCompanySession;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        if ($request->ajax()) {
            return $this->datatable($request);
        }

        return view('company.index');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Tenant::class);

        return view('company.create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $this->authorize('create', Tenant::class);

        /** @var User $user */
        $user = $request->user();

        CompanyProvisioner::create($request->validated(), $user);

        return redirect()
            ->to($this->companyListPath())
            ->with('success', 'Company registered successfully.');
    }

    public function edit(Tenant $company): View
    {
        $this->authorize('update', $company);

        return view('company.edit', [
            'company' => $company,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Tenant $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $company->fill($request->validated());
        $company->save();

        return redirect()
            ->to($this->companyListPath())
            ->with('success', 'Company updated successfully.');
    }

    public function destroy(Request $request, Tenant $company): RedirectResponse
    {
        $this->authorize('update', $company);

        /** @var User $user */
        $user = $request->user();

        if ($user->user_role_id !== UserRole::SUPER_ADMIN || $company->owner_user_id !== $user->id) {
            abort(403);
        }

        $company->delete();

        return redirect()
            ->to($this->companyListPath())
            ->with('success', 'Company deleted successfully.');
    }

    public function enter(Request $request, Tenant $company): RedirectResponse
    {
        $this->authorize('enter', $company);

        SelectedCompanySession::set($company->id);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Entered '.$company->name.'.');
    }

    public function subscription(Request $request, Tenant $company): RedirectResponse
    {
        $this->authorize('block', $company);

        $validated = $request->validate([
            'plan_end_date' => ['required', 'date'],
            'lr_credits' => ['required', 'integer', 'min:0'],
            'truck_credits' => ['required', 'integer', 'min:0'],
        ]);

        $company->update($validated);

        return redirect()
            ->to($this->companyListPath())
            ->with('success', 'Subscription updated for '.$company->name.'.');
    }

    public function invoiceTemplatePreview(Tenant $company, string $template): View
    {
        $this->authorize('view', $company);

        return view('company.template-preview-stub', [
            'company' => $company,
            'template' => $template,
            'type' => 'invoice',
        ]);
    }

    public function lrCopyTemplatePreview(Tenant $company, string $template): View
    {
        $this->authorize('view', $company);

        return view('company.template-preview-stub', [
            'company' => $company,
            'template' => $template,
            'type' => 'lr-copy',
        ]);
    }

    public function freightChallanTemplatePreview(Tenant $company, string $template): View
    {
        $this->authorize('view', $company);

        return view('company.template-preview-stub', [
            'company' => $company,
            'template' => $template,
            'type' => 'freight-challan',
        ]);
    }

    private function companyListPath(): string
    {
        return '/ui/company';
    }

    private function datatable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Tenant::query()->orderByDesc('created_at');

        if ($user->user_role_id === UserRole::SUPER_ADMIN) {
            $query->where('owner_user_id', $user->id);
        }

        $recordsTotal = (clone $query)->count();

        if ($search = $request->input('search.value')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('gst_number', 'like', '%'.$search.'%')
                    ->orWhere('pan_number', 'like', '%'.$search.'%')
                    ->orWhere('contact_person', 'like', '%'.$search.'%')
                    ->orWhere('contact_number', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%');
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = min(max((int) $request->input('length', 10), 1), 100);

        $tenants = $query->skip($start)->take($length)->get();

        $data = $tenants->values()->map(function (Tenant $tenant, int $index) use ($start, $user): array {
            return [
                'id' => $start + $index + 1,
                'name' => e($tenant->name).($tenant->is_blocked ? ' <span class="badge bg-danger">Blocked</span>' : ''),
                'gst_number' => e($tenant->gst_number ?? '—'),
                'pan_number' => e($tenant->pan_number ?? '—'),
                'gst_scheme' => e($tenant->gst_scheme ?? '—'),
                'gst_type' => e($tenant->gst_type ?? '—'),
                'address' => e($tenant->address ?? '—'),
                'contact_person' => e($tenant->contact_person ?? '—'),
                'contact_number' => e($tenant->contact_number ?? '—'),
                'actions' => $this->actionsHtml($tenant, $user),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function actionsHtml(Tenant $tenant, User $user): string
    {
        $html = '<div class="d-flex flex-wrap gap-1 justify-content-center">';

        if ($user->can('enter', $tenant)) {
            $html .= '<a href="'.route('company.enter', $tenant).'" class="btn btn-sm btn-brand-primary">Enter</a>';
        }

        if ($user->can('update', $tenant)) {
            $html .= '<a href="'.route('company.edit', $tenant).'" class="btn btn-sm btn-secondary">Edit</a>';
        }

        if ($user->user_role_id === UserRole::MASTER_ADMIN) {
            $html .= '<button type="button" class="btn btn-sm btn-info js-update-subscription"'
                .' data-company-id="'.e($tenant->id).'"'
                .' data-company-name="'.e($tenant->name).'"'
                .' data-plan-end-date="'.e($tenant->plan_end_date?->toDateString() ?? '').'"'
                .' data-lr-credits="'.e((string) ($tenant->lr_credits ?? 0)).'"'
                .' data-truck-credits="'.e((string) ($tenant->truck_credits ?? 0)).'"'
                .'>Plan</button>';
        }

        if ($user->user_role_id === UserRole::SUPER_ADMIN && $tenant->owner_user_id === $user->id) {
            $html .= '<form method="POST" action="'.route('company.destroy', $tenant).'" class="d-inline">'
                .csrf_field()
                .method_field('DELETE')
                .'<button type="submit" class="btn btn-sm btn-danger">Delete</button>'
                .'</form>';
        }

        $html .= '</div>';

        return $html;
    }
}
