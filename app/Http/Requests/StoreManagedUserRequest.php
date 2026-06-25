<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesManagedUserBranch;
use App\Support\RoleHierarchy;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedUserRequest extends FormRequest
{
    use ValidatesManagedUserBranch;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $assignable = $actor !== null ? RoleHierarchy::assignableRoleIds($actor) : [];
        $ownedTenantIds = $actor !== null ? RoleHierarchy::ownedTenantIds($actor) : [];

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_role_id' => ['required', 'integer', Rule::in($assignable)],
            'tenant_id' => [
                Rule::requiredIf(fn (): bool => $this->requiresTenant()),
                'nullable',
                'uuid',
                Rule::exists('tenants', 'id'),
                Rule::when(
                    $actor !== null && RoleHierarchy::managesTenantUsers($actor),
                    Rule::in($ownedTenantIds),
                ),
            ],
            'document_alerts_live' => ['sometimes', 'boolean'],
            'document_alerts_email' => ['sometimes', 'boolean'],
            'branch_id' => $this->managedUserBranchRulesForStore(),
        ];
    }

    public function requiresTenant(): bool
    {
        $roleId = (int) $this->input('user_role_id');

        return $roleId !== UserRole::SUPER_ADMIN;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_role_id.in' => 'You are not allowed to assign this role.',
            'tenant_id.required' => 'Company is required for this role.',
            'tenant_id.in' => 'You can only assign users to your own companies.',
            ...$this->managedUserBranchMessages(),
        ];
    }
}
