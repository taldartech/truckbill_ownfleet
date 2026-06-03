<?php

namespace App\Http\Requests;

use App\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('permission');

        return $permission instanceof Permission
            && ($this->user()?->can('update', $permission) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_.]*$/',
                Rule::unique('permissions', 'slug')->ignore($permission->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'group' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug must start with a letter and contain only lowercase letters, numbers, dots, and underscores.',
            'group.regex' => 'Group must contain only lowercase letters, numbers, and underscores.',
        ];
    }
}
