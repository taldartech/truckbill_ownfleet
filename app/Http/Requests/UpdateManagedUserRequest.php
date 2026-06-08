<?php

namespace App\Http\Requests;

use App\Support\RoleHierarchy;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = $this->route('managed_user');

        return $managedUser instanceof User
            && ($this->user()?->can('update', $managedUser) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('managed_user');
        $actor = $this->user();
        $assignable = $actor !== null ? RoleHierarchy::assignableRoleIds($actor) : [];
        $ownedTenantIds = $actor !== null ? RoleHierarchy::ownedTenantIds($actor) : [];

        if (RoleHierarchy::isProtectedRole((int) $managedUser->user_role_id)) {
            $assignable = [(int) $managedUser->user_role_id];
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($managedUser->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'user_role_id' => ['sometimes', 'required', 'integer', Rule::in($assignable)],
            'tenant_id' => [
                Rule::requiredIf(fn (): bool => $this->requiresTenant($managedUser)),
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
        ];
    }

    public function requiresTenant(?User $managedUser = null): bool
    {
        $managedUser ??= $this->route('managed_user');
        $roleId = (int) ($this->input('user_role_id') ?? $managedUser?->user_role_id);

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
        ];
    }
}
