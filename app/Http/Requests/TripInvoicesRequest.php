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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $groups = $this->input('groups', []);

            if (! is_array($groups)) {
                return;
            }

            foreach ($groups as $customerId => $group) {
                if (! is_array($group)) {
                    continue;
                }

                $invoiceDate = $group['invoice_date'] ?? $this->input('invoice_date');
                $dueDate = $group['due_date'] ?? null;

                if (! is_string($invoiceDate) || trim($invoiceDate) === '') {
                    continue;
                }

                if (! is_string($dueDate) || trim($dueDate) === '') {
                    continue;
                }

                if ($dueDate < $invoiceDate) {
                    $validator->errors()->add(
                        "groups.{$customerId}.due_date",
                        'Due date cannot be earlier than the invoice date.',
                    );
                }
            }
        });
    }
}
