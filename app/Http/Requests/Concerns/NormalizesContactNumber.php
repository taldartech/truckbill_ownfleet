<?php

namespace App\Http\Requests\Concerns;

trait NormalizesContactNumber
{
    protected function normalizeContactNumberInput(): void
    {
        $this->normalizeDigitsField('contact_number');
    }

    protected function normalizeBranchContactNumberInput(): void
    {
        $this->normalizeDigitsField('branch_contact_number');
    }

    protected function normalizeDigitsField(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $raw = $this->input($field);

        if ($raw === null || $raw === '') {
            $this->merge([$field => null]);

            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $raw);

        $this->merge([
            $field => $digits !== '' ? $digits : null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function contactNumberRules(): array
    {
        return ['nullable', ...$this->tenDigitPhoneRules()];
    }

    /**
     * @return array<int, string>
     */
    protected function branchContactNumberRules(): array
    {
        return ['required', ...$this->tenDigitPhoneRules()];
    }

    /**
     * @return array<int, string>
     */
    protected function tenDigitPhoneRules(): array
    {
        return ['string', 'regex:/^[0-9]{10}$/', 'size:10'];
    }

    /**
     * @return array<string, string>
     */
    protected function contactNumberMessages(): array
    {
        return [
            'contact_number.regex' => 'Please enter a valid 10-digit mobile number.',
            'contact_number.size' => 'Please enter a valid 10-digit mobile number.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function branchContactNumberMessages(): array
    {
        return [
            'branch_contact_number.required' => 'Contact number is required.',
            'branch_contact_number.regex' => 'Please enter a valid 10-digit mobile number.',
            'branch_contact_number.size' => 'Please enter a valid 10-digit mobile number.',
        ];
    }
}
