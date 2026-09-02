<?php

namespace App\Imports;

use App\Enums\TyreType;
use App\Modules\TyreManagement\Services\TyreService;
use App\Support\CurrentTenant;
use App\Tyre;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

class TyresBulkImport implements ToCollection, WithHeadingRow
{
    private int $total = 0;

    private int $successful = 0;

    private int $failed = 0;

    private array $errors = [];

    public function __construct(
        private readonly TyreService $tyreService,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $this->total++;

            // Row 1 contains headings.
            $excelRow = $index + 2;

            try {
                $data = $this->prepareRow($row->toArray());

                $this->validateRow($data);

                $this->importTyre($data);

                $this->successful++;
            } catch (ValidationException $e) {
                $this->failed++;

                $this->errors[] = [
                    'row' => $excelRow,
                    'errors' => $e->errors(),
                ];
            } catch (Throwable $e) {
                $this->failed++;

                $this->errors[] = [
                    'row' => $excelRow,
                    'errors' => [
                        'general' => [
                            $e->getMessage(),
                        ],
                    ],
                ];
            }
        }
    }

    private function prepareRow(array $row): array
    {
        $data = [];

        foreach ($row as $key => $value) {
            $key = trim((string) $key);

            if (is_string($value)) {
                $value = trim($value);
            }

            $data[$key] = $value === '' ? null : $value;
        }

        /*
         * Normalize serial number.
         */
        if (
            array_key_exists('serial_number', $data)
            && $data['serial_number'] !== null
        ) {
            $data['serial_number'] = trim(
                (string) $data['serial_number']
            );
        }

        if (
            array_key_exists('size', $data)
            && $data['size'] !== null
        ) {
            $data['size'] = trim(
                (string) $data['size']
            );
        }
        /*
         * Normalize tyre type.
         */
        if (
            array_key_exists('tyre_type', $data)
            && $data['tyre_type'] !== null
        ) {
            $data['tyre_type'] = strtoupper(
                trim((string) $data['tyre_type'])
            );
        }

        /*
         * Handle Excel dates.
         *
         * User-facing format:
         * d-m-Y
         *
         * Example:
         * 02-09-2026
         */
        foreach ([
            'purchase_date',
            'warranty_valid_until',
        ] as $dateField) {
            if (
                !array_key_exists($dateField, $data)
                || $data[$dateField] === null
            ) {
                continue;
            }

            if (is_numeric($data[$dateField])) {
                try {
                    $data[$dateField] =
                        \PhpOffice\PhpSpreadsheet\Shared\Date
                            ::excelToDateTimeObject(
                                $data[$dateField]
                            )
                            ->format('d-m-Y');
                } catch (Throwable) {
                    // Leave unchanged so validation reports the error.
                }
            } else {
                $data[$dateField] = trim(
                    (string) $data[$dateField]
                );
            }
        }

        return $data;
    }

    private function validateRow(array &$data): void
    {
        $tenantId = CurrentTenant::requireId();

        $validator = Validator::make(
            $data,
            [
                /*
                 * Required fields
                 */
               'serial_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('tyres', 'serial_number')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
                ],

                'brand' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'size' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'purchase_date' => [
                    'required',
                    'date_format:d-m-Y',
                    'before_or_equal:today',
                ],

                'tyre_type' => [
                    'required',
                    Rule::enum(TyreType::class),
                ],

                'expected_life_km' => [
                    'required',
                    'numeric',
                    'min:1000',
                ],

                /*
                 * Optional fields
                 */
                'purchase_cost' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'warranty_valid_until' => [
                    'nullable',
                    'date_format:d-m-Y',
                ],

                'warranty_km' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'current_km' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ],
            [
                'serial_number.required' =>
                    'The serial number is required.',

                'brand.required' =>
                    'The tyre brand is required.',

                'size.required' =>
                    'The tyre size is required.',

                'purchase_date.required' =>
                    'The purchase date is required.',

                'purchase_date.date_format' =>
                    'The purchase date must be in the format d-m-Y.',

                'purchase_date.before_or_equal' =>
                    'The purchase date cannot be in the future.',

                'tyre_type.required' =>
                    'The tyre type is required.',

                'tyre_type.enum' =>
                    'The selected tyre type is invalid.',

                'expected_life_km.required' =>
                    'The expected life is required.',

                'expected_life_km.min' =>
                    'The expected life must be at least 1000 km.',

                'warranty_valid_until.date_format' =>
                    'The warranty valid until date must be in the format d-m-Y.',

                'purchase_cost.numeric' =>
                    'The purchase cost must be a number.',

                'warranty_km.numeric' =>
                    'The warranty kilometres must be a number.',

                'current_km.numeric' =>
                    'The current kilometres must be a number.',
            ]
        );

        /*
         * Serial number must be unique within the tenant.
         *
         * Same rule as StoreTyreRequest.
         */
        if (
            $data['serial_number'] !== null
            && Tyre::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('serial_number', $data['serial_number'])
                ->exists()
        ) {
            $validator->errors()->add(
                'serial_number',
                'This serial number is already registered for a tyre in your company.'
            );
        }

        /*
         * Warranty date cannot be before purchase date.
         */
        if (
            $data['purchase_date'] !== null
            && $data['warranty_valid_until'] !== null
            && $this->isValidDate($data['purchase_date'])
            && $this->isValidDate($data['warranty_valid_until'])
        ) {
            $purchaseDate = Carbon::createFromFormat(
                'd-m-Y',
                $data['purchase_date']
            );

            $warrantyDate = Carbon::createFromFormat(
                'd-m-Y',
                $data['warranty_valid_until']
            );

            if ($warrantyDate->lt($purchaseDate)) {
                $validator->errors()->add(
                    'warranty_valid_until',
                    'The warranty valid until date cannot be before the purchase date.'
                );
            }
        }

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /*
         * Convert user-facing d-m-Y dates to database Y-m-d.
         */
        $data['purchase_date'] = Carbon::createFromFormat(
            'd-m-Y',
            $data['purchase_date']
        )->format('Y-m-d');

        if ($data['warranty_valid_until'] !== null) {
            $data['warranty_valid_until'] = Carbon::createFromFormat(
                'd-m-Y',
                $data['warranty_valid_until']
            )->format('Y-m-d');
        }
    }

    private function isValidDate(?string $date): bool
    {
        if ($date === null) {
            return false;
        }

        try {
            $parsed = Carbon::createFromFormat(
                'd-m-Y',
                $date
            );

            return $parsed->format('d-m-Y') === $date;
        } catch (Throwable) {
            return false;
        }
    }

    private function importTyre(array $data): void
    {
        $this->tyreService->create([
            'tenant_id' => CurrentTenant::requireId(),

            'serial_number' => $data['serial_number'],
            'brand' => $data['brand'],

            'size' => $data['size'],
            'purchase_date' => $data['purchase_date'],

            'purchase_cost' => $data['purchase_cost'] ?? null,

            'warranty_valid_until' =>
                $data['warranty_valid_until'] ?? null,

            'warranty_km' =>
                $data['warranty_km'] ?? null,

            'tyre_type' => $data['tyre_type'],

            'current_km' =>
                $data['current_km'] ?? null,

            'expected_life_km' =>
                $data['expected_life_km'],

            'notes' =>
                $data['notes'] ?? null,
        ]);
    }

    public function result(): array
    {
        return [
            'total' => $this->total,
            'successful' => $this->successful,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }
}