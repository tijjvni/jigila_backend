<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255|unique:roles,name',
            'description'       => 'nullable|string',
            'permissions'       => 'nullable|array',
            'permissions.*'     => Rule::in(Permission::values()),
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'integer|exists:users,id',
        ];
    }
}
