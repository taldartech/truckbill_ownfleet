<?php

namespace App\Http\Requests\Concerns;

use App\BranchSetting;
use Illuminate\Validation\Rule;

trait ValidatesBranchTripSequenceStart
{
    protected function prepareNumberSettingsForValidation(): void
    {
        $settings = $this->input('number_settings');

        if (! is_array($settings)) {
            return;
        }

        foreach ([
            'trip_year_digits',
            'trip_sequence_digits',
            'invoice_year_digits',
            'invoice_sequence_digits',
        ] as $field) {
            if (array_key_exists($field, $settings) && $settings[$field] !== null && $settings[$field] !== '') {
                $settings[$field] = (int) $settings[$field];
            }
        }

        $this->merge(['number_settings' => $settings]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function branchNumberSettingsRules(string $prefix = 'number_settings'): array
    {
        return [
            $prefix => ['sometimes', 'array'],
            "{$prefix}.trip_initial" => ['required_with:'.$prefix, 'string', 'max:50'],
            "{$prefix}.trip_separator_1" => ['nullable', 'string', 'max:5'],
            "{$prefix}.trip_year_type" => ['required_with:'.$prefix, Rule::in(BranchSetting::YEAR_TYPES)],
            "{$prefix}.trip_year_digits" => ['required_with:'.$prefix, Rule::in([2, 4])],
            "{$prefix}.trip_separator_2" => ['nullable', 'string', 'max:5'],
            "{$prefix}.trip_sequence_digits" => ['required_with:'.$prefix, 'integer', 'min:1', 'max:12'],
            "{$prefix}.invoice_initial" => ['required_with:'.$prefix, 'string', 'max:50'],
            "{$prefix}.invoice_separator_1" => ['nullable', 'string', 'max:5'],
            "{$prefix}.invoice_year_type" => ['required_with:'.$prefix, Rule::in(BranchSetting::YEAR_TYPES)],
            "{$prefix}.invoice_year_digits" => ['required_with:'.$prefix, Rule::in([2, 4])],
            "{$prefix}.invoice_separator_2" => ['nullable', 'string', 'max:5'],
            "{$prefix}.invoice_sequence_digits" => ['required_with:'.$prefix, 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function numberSettingsPayload(): array
    {
        $settings = $this->input('number_settings');

        if (! is_array($settings)) {
            return [];
        }

        $fields = [
            'trip_initial',
            'trip_separator_1',
            'trip_year_type',
            'trip_year_digits',
            'trip_separator_2',
            'trip_sequence_digits',
            'invoice_initial',
            'invoice_separator_1',
            'invoice_year_type',
            'invoice_year_digits',
            'invoice_separator_2',
            'invoice_sequence_digits',
        ];

        $payload = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $settings)) {
                continue;
            }

            $value = $settings[$field];

            if (in_array($field, ['trip_separator_1', 'trip_separator_2', 'invoice_separator_1', 'invoice_separator_2'], true)) {
                $payload[$field] = is_string($value) ? $value : '';

                continue;
            }

            if (in_array($field, ['trip_year_digits', 'trip_sequence_digits', 'invoice_year_digits', 'invoice_sequence_digits'], true)) {
                $payload[$field] = (int) $value;

                continue;
            }

            $payload[$field] = is_string($value) ? trim($value) : $value;
        }

        return $payload;
    }
}
