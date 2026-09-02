<?php

namespace App\Imports;

use App\Driver;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Throwable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DriversBulkImport implements ToCollection, WithHeadingRow
{
    private int $total = 0;

    private int $successful = 0;

    private int $failed = 0;

    private array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $this->total++;

            // Row 1 = headings, so first data row is Excel row 2.
            $excelRow = $index + 2;

            try {
                $data = $this->prepareRow($row->toArray());

                $this->validateRow($data);

                $this->importDriver($data);

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
         * Excel may return numeric values as integers/floats.
         * Convert phone numbers to strings.
         */
        if (array_key_exists('phone', $data) && $data['phone'] !== null) {
            $data['phone'] = preg_replace(
                '/\.0$/',
                '',
                (string) $data['phone']
            );
        }

        if (
            array_key_exists('emergency_contact_phone', $data)
            && $data['emergency_contact_phone'] !== null
        ) {
            $data['emergency_contact_phone'] = preg_replace(
                '/\.0$/',
                '',
                (string) $data['emergency_contact_phone']
            );
        }

        /*
         * Same normalization as ValidatesDriverIdentity.
         */
        if (array_key_exists('license_number', $data)) {
            if (
                $data['license_number'] === null
                || trim((string) $data['license_number']) === ''
            ) {
                $data['license_number'] = null;
            } else {
                $data['license_number'] = strtoupper(
                    str_replace(
                        [' ', '-'],
                        '',
                        trim((string) $data['license_number'])
                    )
                );
            }
        }

        if (array_key_exists('aadhaar_number', $data)) {
            if (
                $data['aadhaar_number'] === null
                || trim((string) $data['aadhaar_number']) === ''
            ) {
                $data['aadhaar_number'] = null;
            } else {
                $data['aadhaar_number'] = trim(
                    (string) $data['aadhaar_number']
                );
            }
        }

        if (array_key_exists('pan_number', $data)) {
            if (
                $data['pan_number'] === null
                || trim((string) $data['pan_number']) === ''
            ) {
                $data['pan_number'] = null;
            } else {
                $data['pan_number'] = strtoupper(
                    trim((string) $data['pan_number'])
                );
            }
        }

        /*
         * Excel uses monthly_base_salary.
         * Database uses daily_wage.
         */
        if (array_key_exists('monthly_base_salary', $data)) {
            $data['daily_wage'] = $data['monthly_base_salary'];
        }

        foreach (['joining_date', 'license_expiry_date'] as $dateField) {
            if (
                array_key_exists($dateField, $data)
                && $data[$dateField] !== null
            ) {
                if (is_numeric($data[$dateField])) {
                    $data[$dateField] = ExcelDate::excelToDateTimeObject(
                        $data[$dateField]
                    )->format('d-m-Y');
                } elseif (is_string($data[$dateField])) {
                    $data[$dateField] = trim($data[$dateField]);
                }
            }
        }
        /*
         * Convert Excel Yes/No values into booleans.
         */
        if (array_key_exists('is_active', $data)) {
            $value = $data['is_active'];

            if ($value === null) {
                $data['is_active'] = null;
            } elseif (is_bool($value)) {
                // Already boolean.
            } else {
                $normalized = strtolower(trim((string) $value));

                if (
                    in_array($normalized, [
                        'yes',
                        'y',
                        'true',
                        '1',
                    ], true)
                ) {
                    $data['is_active'] = true;
                } elseif (
                    in_array($normalized, [
                        'no',
                        'n',
                        'false',
                        '0',
                    ], true)
                ) {
                    $data['is_active'] = false;
                }
            }
        }

        return $data;
    }

    private function validateRow(array &$data): void
    {
        $validator = Validator::make(
            $data,
            [
                // Required fields
                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'phone' => [
                    'required',
                    'string',
                    'regex:/^[0-9]{10}$/',
                ],

                'license_number' => [
                    'required',
                    'string',
                    'min:13',
                    'max:18',
                    'regex:/^[A-Z]{2}[A-Z0-9]+$/',
                ],


                'joining_date' => [
                    'required',
                    'date_format:d-m-Y',
                ],

                'daily_wage' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'license_expiry_date' => [
                    'nullable',
                    'date_format:d-m-Y',
                ],


                'address' => [
                    'nullable',
                    'string',
                    'max:500',
                ],

                'aadhaar_number' => [
                    'nullable',
                    'string',
                    'regex:/^[0-9]{12}$/',
                ],

                'pan_number' => [
                    'nullable',
                    'string',
                    'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i',
                ],

                'bank_account_number' => [
                    'nullable',
                    'string',
                    'max:40',
                ],

                'bank_ifsc' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'emergency_contact_name' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'emergency_contact_phone' => [
                    'nullable',
                    'string',
                    'regex:/^[0-9]{10}$/',
                    'max:20',
                ],

                'is_active' => [
                    'nullable',
                    'boolean',
                ],
            ],
            [
                'phone.required' =>
                    'The mobile number is required.',

                'phone.regex' =>
                    'The mobile number must contain exactly 10 digits.',

                'license_number.required' =>
                    'The license number is required.',

                'license_number.min' =>
                    'The license number must be at least 13 characters.',

                'license_number.max' =>
                    'The license number may not exceed 18 characters.',

                'license_number.regex' =>
                    'The license number format is invalid.',

                'joining_date.required' =>
                    'The joining date is required.',

                'joining_date.date' =>
                    'The joining date must be a valid date.',

                'daily_wage.required' =>
                    'The monthly payment is required.',

                'daily_wage.numeric' =>
                    'The monthly payment must be a number.',

                'daily_wage.gt' =>
                    'The monthly payment must be greater than 0.',

                'aadhaar_number.regex' =>
                    'The Aadhaar number must contain exactly 12 digits.',

                'pan_number.regex' =>
                    'The PAN number format is invalid.',

                'emergency_contact_phone.regex' =>
                    'The emergency contact number must contain exactly 10 digits.',

                'license_expiry_date.date_format' =>
                    'The license expiry date must be in the format d-m-Y.',

                'joining_date.date_format' =>
                    'The joining date must be in the format d-m-Y.',
            ]
        );

        $tenantId = CurrentTenant::requireId();

        /*
         * Identity uniqueness:
         * Only active/non-deleted drivers are considered,
         * matching ValidatesDriverIdentity.
         */

        if (
            $data['phone'] !== null
            && Driver::query()
                ->where('tenant_id', $tenantId)
                ->where('phone', $data['phone'])
                ->whereNull('deleted_at')
                ->exists()
        ) {
            $validator->errors()->add(
                'phone',
                'This mobile number is already registered for another driver in your company.'
            );
        }

        if (
            $data['license_number'] !== null
            && Driver::query()
                ->where('tenant_id', $tenantId)
                ->where('license_number', $data['license_number'])
                ->whereNull('deleted_at')
                ->exists()
        ) {
            $validator->errors()->add(
                'license_number',
                'This license number is already registered for another driver in your company.'
            );
        }

        if (
            $data['aadhaar_number'] !== null
            && Driver::query()
                ->where('tenant_id', $tenantId)
                ->where('aadhaar_number', $data['aadhaar_number'])
                ->whereNull('deleted_at')
                ->exists()
        ) {
            $validator->errors()->add(
                'aadhaar_number',
                'This Aadhaar number is already registered for another driver in your company.'
            );
        }

        if (
            $data['pan_number'] !== null
            && Driver::query()
                ->where('tenant_id', $tenantId)
                ->where('pan_number', $data['pan_number'])
                ->whereNull('deleted_at')
                ->exists()
        ) {
            $validator->errors()->add(
                'pan_number',
                'This PAN number is already registered for another driver in your company.'
            );
        }

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data['daily_wage'] = number_format(
            (float) $data['daily_wage'],
            2,
            '.',
            ''
        );
    }

    private function importDriver(array $data): void
    {
        $tenantId = CurrentTenant::requireId();

        Driver::query()->create([
            'tenant_id' => $tenantId,

            'name' => $data['name'],
            'phone' => $data['phone'],
            'license_number' => $data['license_number'],
            'license_expiry_date' => $data['license_expiry_date'],
            'address' => $data['address'],
            'aadhaar_number' => $data['aadhaar_number'],
            'pan_number' => $data['pan_number'],
            'bank_account_number' => $data['bank_account_number'],
            'bank_ifsc' => $data['bank_ifsc'],
            'joining_date' => $data['joining_date'],
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],

            // monthly_base_salary → daily_wage
            'daily_wage' => $data['daily_wage'],

            'is_active' => $data['is_active'] ?? true,

            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
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