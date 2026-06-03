<?php

namespace App\Http\Requests;

use App\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permission::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]*$/', 'unique:permissions,slug'],
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
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
