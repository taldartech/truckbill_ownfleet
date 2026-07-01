<?php

namespace App\Http\Requests;

use App\Branch;
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
