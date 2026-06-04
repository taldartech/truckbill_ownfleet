<?php

namespace App\Support;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSessionManager
{
    /**
     * Keep only the current browser session for this user (one device at a time).
     */
    public static function invalidateOtherSessions(User $user, ?string $exceptSessionId = null): void
    {
        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }

    /**
     * Invalidate remember-me cookies on every other device.
     */
    public static function rotateRememberToken(User $user): void
    {
        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();
    }
}
