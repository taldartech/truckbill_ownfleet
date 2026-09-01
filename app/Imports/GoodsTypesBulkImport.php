<?php

namespace App\Imports;

use App\GoodsType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class GoodsTypesBulkImport extends BulkImport
{
    private array $uploadedNames = [];

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $userId,
    ) {}

    protected function processRow(
        array $row,
        int $excelRow,
    ): void {
        $name = trim((string) ($row['name'] ?? ''));

        /*
         * Validate row.
         */
        $validator = Validator::make(
            ['name' => $name],
            [
                'name' => [
                    'required',
                    'string',
                    'max:100',
                ],
            ],
            [
                'name.required' =>
                    'Goods type name is required.',

                'name.string' =>
                    'Goods type name must be text.',

                'name.max' =>
                    'Goods type name may not exceed 100 characters.',
            ],
        );

        if ($validator->fails()) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => $validator
                    ->errors()
                    ->all(),
            ];
            return;
        }

        /*
         * Normalize name for duplicate comparison.
         */
        $normalizedName = mb_strtolower($name);

        /*
         * Duplicate inside uploaded file.
         */
        if (isset($this->uploadedNames[$normalizedName])) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    'This goods type is duplicated in the uploaded file.',
                ],
            ];

            return;
        }

        /*
         * Duplicate already existing in this company.
         */
        $exists = GoodsType::query()
            ->where('tenant_id', $this->tenantId)
            ->whereRaw(
                'LOWER(name) = ?',
                [$normalizedName],
            )
            ->exists();

        if ($exists) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    'This goods type already exists.',
                ],
            ];

            return;
        }

        /*
         * Remember name so duplicate rows later
         * in the same file are rejected.
         */
        $this->uploadedNames[$normalizedName] = true;

        /*
         * Insert valid row.
         */
        try {
            GoodsType::query()->create([
                'tenant_id' => $this->tenantId,
                'name' => $name,
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);

            $this->successful++;
        } catch (QueryException $exception) {
            /*
             * Additional protection against a race condition
             * between duplicate checking and insertion.
             */
            if ($this->isDuplicateException($exception)) {
                $this->failed++;

                $this->errors[] = [
                    'row' => $excelRow,
                    'value' => $name,
                    'errors' => [
                        'This goods type already exists.',
                    ],
                ];

                return;
            }

            throw $exception;
        }
    }

    private function isDuplicateException(
        QueryException $exception,
    ): bool {
        return (int) $exception->getCode() === 23000
            && str_contains(
                strtolower($exception->getMessage()),
                'duplicate',
            );
    }
}