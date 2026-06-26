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

class UpdateBranchRequest extends FormRequest
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
        /** @var Branch|null $branch */
        $branch = $this->route('branch');

        return $branch !== null && ($this->user()?->can('update', $branch) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        $ownedCompanyIds = $this->user() !== null
            ? RoleHierarchy::ownedTenantIds($this->user())
            : [];

        $companyId = (string) ($this->input('company_id') ?? $branch->company_id);

        return [
            'company_id' => ['sometimes', 'required', 'uuid', Rule::in($ownedCompanyIds)],
            'branch_name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('branches', 'branch_name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($branch->id),
            ],
            'branch_contact_number' => [
                'sometimes',
                ...$this->branchContactNumberRules(),
            ],
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

            /** @var Branch $branch */
            $branch = $this->route('branch');
            $companyId = (string) ($this->input('company_id') ?? $branch->company_id);

            if ($this->has('branch_contact_number')) {
                $this->validateBranchContactNumberUniqueness(
                    $validator,
                    $companyId,
                    (string) $this->input('branch_contact_number'),
                    $branch->id,
                );
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->boolean('head_office')) {
                $existingHeadOffice = Branch::query()
                    ->where('company_id', $companyId)
                    ->where('head_office', true)
                    ->whereKeyNot($branch->id)
                    ->exists();

                if ($existingHeadOffice) {
                    $validator->errors()->add('head_office', 'This company already has a head office branch.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateBranchTripSequenceStartChange($validator, $branch);
        });
    }
}
