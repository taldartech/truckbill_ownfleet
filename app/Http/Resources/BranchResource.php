<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use App\Support\TripNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->relationLoaded('settings') ? $this->settings : $this->settings()->first();
        $generator = app(TripNumberGenerator::class);

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_name' => $this->branch_name,
            'branch_address' => $this->branch_address,
            'branch_contact_number' => $this->branch_contact_number,
            'head_office' => (bool) $this->head_office,
            'number_settings' => $settings !== null ? [
                'trip_initial' => $settings->trip_initial,
                'trip_separator_1' => $settings->trip_separator_1,
                'trip_year_type' => $settings->trip_year_type,
                'trip_year_digits' => (int) $settings->trip_year_digits,
                'trip_separator_2' => $settings->trip_separator_2,
                'trip_sequence_digits' => (int) $settings->trip_sequence_digits,
                'trip_example' => $generator->formatExample($settings, TripNumberGenerator::DOCUMENT_TRIP),
                'invoice_initial' => $settings->invoice_initial,
                'invoice_separator_1' => $settings->invoice_separator_1,
                'invoice_year_type' => $settings->invoice_year_type,
                'invoice_year_digits' => (int) $settings->invoice_year_digits,
                'invoice_separator_2' => $settings->invoice_separator_2,
                'invoice_sequence_digits' => (int) $settings->invoice_sequence_digits,
                'invoice_example' => $generator->formatExample($settings, TripNumberGenerator::DOCUMENT_INVOICE),
            ] : null,
            'inherit_company_gst' => (bool) ($this->inherit_company_gst ?? true),
            'use_company_logo' => (bool) ($this->use_company_logo ?? true),
            'branch_logo' => $this->branch_logo,
            'logo_url' => $this->logoPublicUrl(),
            'registration_type' => $this->registration_type?->value ?? $this->registration_type,
            'tax_liability' => $this->tax_liability?->value ?? $this->tax_liability,
            'gst_number' => $this->gst_number,
            'state_id' => $this->state_id,
            'gst_rate' => $this->gst_rate !== null ? (float) $this->gst_rate : null,
            'state_name' => $this->whenLoaded('state', fn () => $this->state?->name),
            'effective_gst' => $this->when(
                $this->relationLoaded('company') || $this->company_id,
                function () {
                    $resolver = app(\App\Support\GstSettingsResolver::class);
                    $branch = $this->resource;
                    if (! $branch->relationLoaded('company')) {
                        $branch->load(['company.state', 'state']);
                    } elseif (! $branch->relationLoaded('state')) {
                        $branch->load('state');
                    }

                    $settings = $resolver->forBranch($branch);

                    return [
                        'inherit_company_gst' => $settings['inherit_company_gst'],
                        'registration_type' => $settings['registration_type']?->value,
                        'tax_liability' => $settings['tax_liability']?->value,
                        'gst_number' => $settings['gst_number'],
                        'gst_rate' => $settings['gst_rate'],
                        'state_id' => $settings['state_id'],
                        'state_code' => $settings['state_code'],
                        'source' => $settings['source'],
                    ];
                }
            ),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'registration_type' => $this->company?->registration_type?->value ?? $this->company?->registration_type,
                'tax_liability' => $this->company?->tax_liability?->value ?? $this->company?->tax_liability,
                'gst_number' => $this->company?->gst_number,
                'gst_rate' => $this->company?->gst_rate !== null ? (float) $this->company->gst_rate : null,
                'state_id' => $this->company?->state_id,
                'company_logo' => $this->company?->company_logo,
                'logo_url' => $this->company?->logoPublicUrl(),
            ]),
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
