<?php

namespace App\Http\Requests;

use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTyreInspectionRequest extends FormRequest
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
            'vehicle_id' => ['nullable', 'uuid', 'exists:vehicles,id'],
            'inspected_at' => array_merge(
                ['required', 'date', 'before_or_equal:today'],
                $purchaseDate ? ["after_or_equal:{$purchaseDate}"] : [],
            ),
            'tread_depth_mm' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'pressure_psi' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'result' => ['nullable', 'string', Rule::in(['PASS', 'FAIL', 'ATTENTION'])],
            'next_due_at' => ['nullable', 'date', 'after_or_equal:inspected_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'inspector_id' => ['nullable', 'uuid', 'exists:users,id'],
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
