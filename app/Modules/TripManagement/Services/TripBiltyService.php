<?php

namespace App\Modules\TripManagement\Services;

use App\Branch;
use App\Enums\TaxLiability;
use App\Support\GstSettingsResolver;
use App\Tenant;
use App\Trip;
use App\TripRouteLeg;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

class TripBiltyService
{
    public function __construct(
        private readonly GstSettingsResolver $gstSettingsResolver,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function groupsForTrip(Trip $trip): array
    {
        $trip->loadMissing([
            'vehicle',
            'driver',
            'routeLegs.customer',
            'routeLegs.pickup',
        ]);

        $lrDate = $trip->start_at?->toDateString() ?? now()->toDateString();

        $legs = $trip->routeLegs
            ->filter(fn (TripRouteLeg $leg) => $leg->isRevenueLeg())
            ->filter(fn (TripRouteLeg $leg) => filled($leg->customer_id))
            ->values();

        if ($legs->isEmpty()) {
            throw ValidationException::withMessages([
                'trip' => ['There are no revenue routes with customers to print a bilty for this trip.'],
            ]);
        }

        $bilties = [];

        foreach ($legs as $index => $leg) {
            $customer = $leg->customer;
            $fromLocation = $leg->from_city ?: '—';
            $toLocation = $leg->to_city ?: '—';
            $weight = (float) $leg->weight_ton;
            $packages = (int) ($leg->no_of_units ?? 0);
            $declaredValue = (float) ($leg->goods_value ?? 0);
            $distance = (float) ($leg->distance_km ?? 0);
            $copyIndex = $index + 1;

            $lrNumber = filled($leg->lr_number)
                ? (string) $leg->lr_number
                : sprintf('%s-%d', $trip->trip_number, $copyIndex);

            $consignor = filled($leg->consignor)
                ? (string) $leg->consignor
                : $fromLocation;

            $consignee = filled($leg->consignee)
                ? (string) $leg->consignee
                : $toLocation;

            $consignorAddress = filled($leg->from_address)
                ? (string) $leg->from_address
                : null;

            $consigneeAddress = filled($leg->to_address)
                ? (string) $leg->to_address
                : null;

            $bilties[] = [
                'customer_id' => (int) $leg->customer_id,
                'customer_name' => $customer?->name ?? '—',
                'customer_gst_number' => $customer?->gst_number,
                'route_leg_id' => $leg->id,
                'pickup_code' => $leg->pickup?->pickup_code,
                'drop_code' => $leg->drop_code,
                'lr_number' => $lrNumber,
                'lr_date' => $lrDate,
                'trip_number' => $trip->trip_number,
                'truck_number' => $trip->vehicle?->registration_number ?? '—',
                'driver_name' => $trip->driver?->name,
                'invoice_number' => $leg->invoice_number,
                'consignor' => $consignor,
                'consignor_address' => $consignorAddress,
                'consignee' => $consignee,
                'consignee_address' => $consigneeAddress,
                'from_location' => $fromLocation,
                'to_location' => $toLocation,
                'origin' => $fromLocation,
                'destination' => $toLocation,
                'distance_km' => $distance,
                'pickup_date' => $leg->pickup_date?->toDateString()
                    ?? $trip->start_at?->toDateString(),
                'goods_description' => filled($leg->goods_name) ? (string) $leg->goods_name : '—',
                'temperature_requirement' => 'As Required',
                'load_type' => $weight > 0 ? 'Full Truck Load' : 'Empty',
                'total_weight_ton' => $weight,
                'no_of_packages' => $packages > 0 ? $packages : null,
                'declared_value' => $declaredValue > 0 ? $declaredValue : null,
                'ewaybill_no' => $leg->ewaybill_no,
                'ewaybill_expiry' => $leg->ewaybill_expiry?->toDateString(),
                'routes' => [[
                    'id' => $leg->id,
                    'sequence_no' => (int) $leg->sequence_no,
                    'from_location' => $fromLocation,
                    'to_location' => $toLocation,
                    'goods_name' => $leg->goods_name,
                    'weight_ton' => $weight,
                    'freight_amount' => (float) $leg->freight_amount,
                    'lr_number' => $leg->lr_number,
                    'invoice_number' => $leg->invoice_number,
                    'consignor' => $leg->consignor,
                    'from_address' => $leg->from_address,
                    'consignee' => $leg->consignee,
                    'to_address' => $leg->to_address,
                    'distance_km' => $distance,
                    'pickup_date' => $leg->pickup_date?->toDateString(),
                    'no_of_units' => $packages,
                    'goods_value' => $declaredValue,
                    'ewaybill_no' => $leg->ewaybill_no,
                    'ewaybill_expiry' => $leg->ewaybill_expiry?->toDateString(),
                ]],
                'freight_amount' => round((float) $leg->freight_amount, 2),
                'route_count' => 1,
            ];
        }

        return $bilties;
    }

    public function pdf(Trip $trip, bool $showFreight = false)
    {
        $bilties = $this->groupsForTrip($trip);

        $branch = $trip->branch_id
            ? Branch::query()->with(['company.state', 'state'])->find($trip->branch_id)
            : null;

        $company = $branch?->company
            ?? ($trip->tenant_id
                ? Tenant::query()->with('state')->find($trip->tenant_id)
                : null);

        $gstSettings = $branch !== null
            ? $this->gstSettingsResolver->forBranch($branch, $company)
            : $this->gstSettingsResolver->fromCompany($company);

        $companyLine = implode(', ', array_filter([
            $company?->address,
            $company?->state?->name,
            $company?->contact_number ? 'Ph. '.$company->contact_number : null,
        ]));

        return Pdf::loadView('trips.pdf.bilty-copy', [
            'bilties' => $bilties,
            'company' => $company,
            'company_line' => $companyLine,
            'gst_number' => $gstSettings['gst_number'] ?? $company?->gst_number,
            'pan_number' => $company?->pan_number,
            'logo_path' => ($branch ?? $company)?->logoFilesystemPathForPdf(),
            'document_color' => $company?->documentColor(),
            'document_text_color' => $company?->documentTextColor(),
            'document_color_soft' => $company?->documentColorWithOpacity(0.2),
            'is_rcm' => ($gstSettings['tax_liability'] ?? null) === TaxLiability::ReverseCharge,
            'show_freight' => $showFreight,
            'trip' => $trip,
        ])->setPaper('a4');
    }

}
