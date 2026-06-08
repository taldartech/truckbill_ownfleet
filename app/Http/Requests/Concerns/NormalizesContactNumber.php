<?php

namespace App\Http\Requests\Concerns;

trait NormalizesContactNumber
{
    protected function normalizeContactNumberInput(): void
    {
        if (! $this->has('contact_number')) {
            return;
        }

        $raw = $this->input('contact_number');

        if ($raw === null || $raw === '') {
            $this->merge(['contact_number' => null]);

            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $raw);

        $this->merge([
            'contact_number' => $digits !== '' ? $digits : null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function contactNumberRules(): array
    {
        return ['nullable', 'string', 'regex:/^[0-9]{10}$/', 'size:10'];
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
}
