<?php

namespace App\Support;

use App\Enums\PayrollPayDaysMode;
use App\Tenant;
use App\User;
use Illuminate\Support\Str;

class CompanyProvisioner
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes, User $owner): Tenant
    {
        $name = (string) ($attributes['name'] ?? '');

        $tenant = Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => Tenant::ensureUniqueSlug($name),
            'short_name' => Tenant::ensureUniqueShortName(
                collect(explode(' ', $name))->map(fn (string $part) => Str::substr($part, 0, 1))->join('')
            ),
            'timezone' => $attributes['timezone'] ?? 'Asia/Kolkata',
            'is_active' => true,
            'is_blocked' => false,
            'gst_number' => $attributes['gst_number'] ?? null,
            'pan_number' => $attributes['pan_number'] ?? null,
            'registration_type' => $attributes['registration_type'] ?? null,
            'tax_liability' => $attributes['tax_liability'] ?? null,
            'state_id' => $attributes['state_id'] ?? null,
            'gst_rate' => $attributes['gst_rate'] ?? null,
            'address' => $attributes['address'] ?? null,
            'contact_person' => $attributes['contact_person'] ?? null,
            'contact_number' => $attributes['contact_number'] ?? null,
            'plan_end_date' => now()->addDays(14)->toDateString(),
            'lr_credits' => 150,
            'truck_credits' => 0,
            'payroll_pay_days_mode' => isset($attributes['payroll_pay_days_mode'])
                ? (PayrollPayDaysMode::tryFrom((string) $attributes['payroll_pay_days_mode']) ?? PayrollPayDaysMode::Calendar)
                : PayrollPayDaysMode::Calendar,
        ]);

        TyreLayoutDefaults::seedForTenant($tenant->id);
        TruckTypeDefaults::seedForTenant($tenant->id);
        DocumentTypeDefaults::seedForTenant($tenant->id);

        return $tenant;
    }
}
