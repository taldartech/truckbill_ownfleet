<?php

namespace App\Modules\TripManagement\Services;

use App\Branch;
use App\Enums\TaxLiability;
use App\Support\GstSettingsResolver;
use App\Tenant;
use App\Trip;
use App\TripRouteLeg;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
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
        ]);

        $legs = $trip->routeLegs
            ->filter(fn (TripRouteLeg $leg) => $leg->isRevenueLeg())
            ->filter(fn (TripRouteLeg $leg) => filled($leg->customer_id))
            ->sortBy('sequence_no')
            ->values();

        if ($legs->isEmpty()) {
            throw ValidationException::withMessages([
                'trip' => ['There are no revenue routes with customers to print a bilty for this trip.'],
            ]);
        }

        $grouped = $legs->groupBy(fn (TripRouteLeg $leg) => (string) $leg->customer_id);
        $bilties = [];
        $index = 1;

        foreach ($grouped as $customerId => $customerLegs) {
            /** @var Collection<int, TripRouteLeg> $customerLegs */
            $first = $customerLegs->first();
            $last = $customerLegs->last();
            $customer = $first?->customer;

            $routes = $customerLegs->map(function (TripRouteLeg $leg): array {
                return [
                    'id' => $leg->id,
                    'sequence_no' => (int) $leg->sequence_no,
                    'from_location' => $leg->from_city ?: ($leg->from_location ?: '—'),
                    'to_location' => $leg->to_city ?: ($leg->to_location ?: '—'),
                    'goods_name' => $leg->goods_name,
                    'weight_ton' => (float) $leg->weight_ton,
                    'freight_amount' => (float) $leg->freight_amount,
                    'lr_number' => $leg->lr_number,
                    'invoice_number' => $leg->invoice_number,
                    'consignor' => $leg->consignor,
                    'consignee' => $leg->consignee,
                    'distance_km' => (float) ($leg->distance_km ?? 0),
                    'pickup_date' => $leg->pickup_date?->toDateString(),
                    'no_of_units' => (int) ($leg->no_of_units ?? 0),
                    'goods_value' => (float) ($leg->goods_value ?? 0),
                    'ewaybill_no' => $leg->ewaybill_no,
                    'ewaybill_expiry' => $leg->ewaybill_expiry?->toDateString(),
                ];
            })->values()->all();

            $lrNumber = collect($routes)
                ->pluck('lr_number')
                ->filter(fn ($value) => filled($value))
                ->first();

            if ($lrNumber === null) {
                $lrNumber = sprintf('%s-%d', $trip->trip_number, $index);
            }

            $invoiceNumber = collect($routes)
                ->pluck('invoice_number')
                ->filter(fn ($value) => filled($value))
                ->first();

            $totalWeight = round($customerLegs->sum(fn (TripRouteLeg $leg) => (float) $leg->weight_ton), 3);
            $totalDistance = round($customerLegs->sum(fn (TripRouteLeg $leg) => (float) ($leg->distance_km ?? 0)), 2);
            $totalPackages = (int) $customerLegs->sum(fn (TripRouteLeg $leg) => (int) ($leg->no_of_units ?? 0));
            $declaredValue = round($customerLegs->sum(fn (TripRouteLeg $leg) => (float) ($leg->goods_value ?? 0)), 2);

            $goodsNames = $customerLegs
                ->pluck('goods_name')
                ->filter(fn ($value) => filled($value))
                ->unique()
                ->values();

            $pickupDate = $customerLegs
                ->map(fn (TripRouteLeg $leg) => $leg->pickup_date)
                ->filter()
                ->sort()
                ->first();

            $ewaybillNo = $customerLegs
                ->pluck('ewaybill_no')
                ->filter(fn ($value) => filled($value))
                ->first();

            $ewaybillExpiry = $customerLegs
                ->map(fn (TripRouteLeg $leg) => $leg->ewaybill_expiry)
                ->filter()
                ->sort()
                ->first();

            $consignor = filled($first?->consignor)
                ? (string) $first->consignor
                : ($routes[0]['from_location'] ?? '—');

            $consignee = filled($last?->consignee)
                ? (string) $last->consignee
                : ($routes[array_key_last($routes)]['to_location'] ?? '—');

            $bilties[] = [
                'customer_id' => (int) $customerId,
                'customer_name' => $customer?->name ?? '—',
                'customer_gst_number' => $customer?->gst_number,
                'lr_number' => $lrNumber,
                'lr_date' => $trip->start_at?->toDateString() ?? now()->toDateString(),
                'trip_number' => $trip->trip_number,
                'truck_number' => $trip->vehicle?->registration_number ?? '—',
                'driver_name' => $trip->driver?->name,
                'invoice_number' => $invoiceNumber,
                'consignor' => $consignor,
                'consignee' => $consignee,
                'from_location' => $routes[0]['from_location'] ?? '—',
                'to_location' => $routes[array_key_last($routes)]['to_location'] ?? '—',
                'origin' => $routes[0]['from_location'] ?? '—',
                'destination' => $routes[array_key_last($routes)]['to_location'] ?? '—',
                'distance_km' => $totalDistance,
                'pickup_date' => $pickupDate?->toDateString()
                    ?? $trip->start_at?->toDateString(),
                'goods_description' => $goodsNames->implode(', ') ?: '—',
                'temperature_requirement' => 'As Required',
                'load_type' => $totalWeight > 0 ? 'Full Truck Load' : 'Empty',
                'total_weight_ton' => $totalWeight,
                'no_of_packages' => $totalPackages > 0 ? $totalPackages : null,
                'declared_value' => $declaredValue > 0 ? $declaredValue : null,
                'ewaybill_no' => $ewaybillNo,
                'ewaybill_expiry' => $ewaybillExpiry?->toDateString(),
                'routes' => $routes,
                'freight_amount' => round($customerLegs->sum(fn (TripRouteLeg $leg) => (float) $leg->freight_amount), 2),
                'route_count' => count($routes),
            ];

            $index++;
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

        $companyLine = collect([
            $company?->address,
            $company?->state?->name,
            $company?->contact_number ? 'Ph. '.$company->contact_number : null,
        ])->filter()->implode(', ');

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
