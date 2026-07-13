<?php

namespace App\Http\Requests;

use App\Enums\TyreExpenseType;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTyreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tyre = $this->route('tyre');

        return $tyre instanceof Tyre && ($this->user()?->can('update', $tyre) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purchaseDate = $this->resolveTyrePurchaseDate();

        return [
            'expense_type' => ['nullable', Rule::enum(TyreExpenseType::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => array_merge(
                ['required', 'date', 'before_or_equal:today'],
                $purchaseDate ? ["after_or_equal:{$purchaseDate}"] : [],
            ),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function resolveTyrePurchaseDate(): ?string
    {
        $tyre = $this->route('tyre');

        return $tyre instanceof Tyre && $tyre->purchase_date
            ? $tyre->purchase_date->toDateString()
            : null;
    }
}
