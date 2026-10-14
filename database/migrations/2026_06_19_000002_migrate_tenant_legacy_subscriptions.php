<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_subscriptions')) {
            return;
        }

        $includedBranches = 2;
        $usersPerBranch = 3;
        $includedTrucks = 50;
        $today = now()->toDateString();

        $tenants = DB::table('tenants')->orderBy('created_at')->get();

        foreach ($tenants as $tenant) {
            if (DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            $planEndDate = $tenant->plan_end_date ?? null;
            $createdDate = $tenant->created_at
                ? \Carbon\Carbon::parse($tenant->created_at)->toDateString()
                : $today;

            $status = 'trial';
            $startsOn = $createdDate;
            $endsOn = \Carbon\Carbon::parse($createdDate)->addDays(15)->toDateString();
            $trialEndsOn = $endsOn;

            if ($planEndDate !== null) {
                $endsOn = $planEndDate;
                $startsOn = \Carbon\Carbon::parse($planEndDate)->subYear()->addDay()->toDateString();
                $trialEndsOn = null;
                $status = $planEndDate >= $today ? 'active' : 'expired';
            }

            $truckCredits = (int) ($tenant->truck_credits ?? 0);
            $addonTrucks = max(0, $truckCredits - $includedTrucks);

            DB::table('tenant_subscriptions')->insert([
                'tenant_id' => $tenant->id,
                'status' => $status,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'trial_ends_on' => $trialEndsOn,
                'included_branches' => $includedBranches,
                'users_per_branch' => $usersPerBranch,
                'included_lr_credits' => 0,
                'included_trucks' => $includedTrucks,
                'addon_branches' => 0,
                'addon_users' => 0,
                'addon_trucks' => $addonTrucks,
                'addon_lr_credits' => 0,
                'lr_credits_remaining' => (int) ($tenant->lr_credits ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_subscriptions') || ! Schema::hasTable('tenants')) {
            return;
        }

        $tenants = DB::table('tenants')->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $subscription = DB::table('tenant_subscriptions')
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($subscription === null) {
                continue;
            }

            $planEndDate = $subscription->trial_ends_on !== null ? null : $subscription->ends_on;
            $truckCredits = (int) $subscription->included_trucks + (int) $subscription->addon_trucks;

            $updates = [];

            if (Schema::hasColumn('tenants', 'plan_end_date')) {
                $updates['plan_end_date'] = $planEndDate;
            }

            if (Schema::hasColumn('tenants', 'lr_credits')) {
                $updates['lr_credits'] = (int) $subscription->lr_credits_remaining;
            }

            if (Schema::hasColumn('tenants', 'truck_credits')) {
                $updates['truck_credits'] = $truckCredits;
            }

            if ($updates !== []) {
                DB::table('tenants')->where('id', $tenant->id)->update($updates);
            }
        }

        DB::table('tenant_subscriptions')->delete();
    }
};
