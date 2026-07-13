<?php

namespace App\Http\Requests;

use App\Branch;
use App\Enums\GstRegistrationType;
use App\Enums\TaxLiability;
use App\Http\Requests\Concerns\NormalizesContactNumber;
use App\Http\Requests\Concerns\ValidatesBranchContactUniqueness;
use App\Http\Requests\Concerns\ValidatesBranchTripSequenceStart;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBranchRequest extends FormRequest
{
    use NormalizesContactNumber;
    use ValidatesBranchContactUniqueness;
    use ValidatesBranchTripSequenceStart;

    protected function prepareForValidation(): void
    {
        $this->normalizeBranchContactNumberInput();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Branch::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ownedCompanyIds = $this->user() !== null
            ? RoleHierarchy::ownedTenantIds($this->user())
            : [];

        return [
            'company_id' => ['required', 'uuid', Rule::in($ownedCompanyIds)],
            'branch_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('branches', 'branch_name')->where(
                    fn ($query) => $query
                        ->where('company_id', $this->input('company_id'))
                        ->whereNull('deleted_at')
                ),
            ],
            'branch_contact_number' => $this->branchContactNumberRules(),
            'branch_address' => ['nullable', 'string', 'max:1000'],
            'head_office' => ['sometimes', 'boolean'],
            'trip_sequence_start' => $this->tripSequenceStartRules(),
            'inherit_company_gst' => ['sometimes', 'boolean'],
            'registration_type' => ['nullable', Rule::enum(GstRegistrationType::class)],
            'tax_liability' => ['nullable', Rule::enum(TaxLiability::class)],
            'gst_number' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->branchContactNumberMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (string) $this->input('company_id');

            $this->validateBranchContactNumberUniqueness(
                $validator,
                $companyId,
                (string) $this->input('branch_contact_number'),
            );

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->boolean('head_office')) {
                return;
            }

            $existingHeadOffice = Branch::query()
                ->where('company_id', $companyId)
                ->where('head_office', true)
                ->exists();

            if ($existingHeadOffice) {
                $validator->errors()->add('head_office', 'This company already has a head office branch.');
            }
        });
    }
}
