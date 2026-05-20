<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id'    => 'required|integer|exists:roles,id',
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }
}
