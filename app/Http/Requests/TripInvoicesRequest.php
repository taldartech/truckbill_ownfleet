<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TripInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'groups' => ['nullable', 'array'],
            'groups.*.invoice_date' => ['nullable', 'date'],
            'groups.*.due_date' => ['nullable', 'date'],
            'groups.*.invoice_sequence' => ['nullable', 'integer', 'min:1', 'max:999999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $groups = $this->input('groups', []);

            if (! is_array($groups)) {
                return;
            }

            $seenSequences = [];

            foreach ($groups as $customerId => $group) {
                if (! is_array($group)) {
                    continue;
                }

                $invoiceDate = $group['invoice_date'] ?? $this->input('invoice_date');
                $dueDate = $group['due_date'] ?? null;

                if (is_string($invoiceDate) && trim($invoiceDate) !== ''
                    && is_string($dueDate) && trim($dueDate) !== ''
                    && $dueDate < $invoiceDate
                ) {
                    $validator->errors()->add(
                        "groups.{$customerId}.due_date",
                        'Due date cannot be earlier than the invoice date.',
                    );
                }

                if (! array_key_exists('invoice_sequence', $group) || $group['invoice_sequence'] === null || $group['invoice_sequence'] === '') {
                    continue;
                }

                $sequence = (int) $group['invoice_sequence'];
                $dateKey = is_string($invoiceDate) ? $invoiceDate : '';
                $key = $dateKey.'|'.$sequence;

                if (isset($seenSequences[$key])) {
                    $validator->errors()->add(
                        "groups.{$customerId}.invoice_sequence",
                        'Each invoice group must use a distinct sequence for the same invoice date.',
                    );

                    continue;
                }

                $seenSequences[$key] = true;
            }
        });
    }
}
