<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')->id;

        return [
            'name'          => "sometimes|string|max:255|unique:roles,name,{$roleId}",
            'description'   => 'sometimes|nullable|string',
            'permissions'   => 'sometimes|array',
            'permissions.*' => Rule::in(Permission::values()),
        ];
    }
}
