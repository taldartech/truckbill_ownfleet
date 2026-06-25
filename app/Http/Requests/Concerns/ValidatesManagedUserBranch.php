<?php

namespace App\Http\Requests\Concerns;

use App\Support\RoleHierarchy;
use Illuminate\Validation\Rule;

trait ValidatesManagedUserBranch
{
    public function requiresBranch(): bool
    {
        $roleId = (int) $this->input('user_role_id');

        return RoleHierarchy::requiresBranchAssignment($roleId);
    }

    /**
     * @return array<int, \Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function managedUserBranchRulesForStore(): array
    {
        return $this->managedUserBranchRules(required: true);
    }

    /**
     * @return array<int, \Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function managedUserBranchRulesForUpdate(): array
    {
        return $this->managedUserBranchRules(required: false);
    }

    /**
     * @return array<int, \Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function managedUserBranchRules(bool $required = true): array
    {
        $tenantId = $this->input('tenant_id');

        if ($tenantId === null) {
            $managedUser = $this->route('managed_user');
            $tenantId = $managedUser instanceof \App\User ? $managedUser->tenant_id : null;
        }

        $rules = [
            'nullable',
            'integer',
            Rule::exists('branches', 'id')->where(
                fn ($query) => $query->where('company_id', $tenantId)
            ),
        ];

        if ($required) {
            array_unshift($rules, Rule::requiredIf(fn (): bool => $this->requiresBranch()));
        } else {
            array_unshift($rules, 'sometimes');
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function managedUserBranchMessages(): array
    {
        return [
            'branch_id.required' => 'Branch is required for this role.',
            'branch_id.exists' => 'Selected branch does not belong to the chosen company.',
        ];
    }
}
