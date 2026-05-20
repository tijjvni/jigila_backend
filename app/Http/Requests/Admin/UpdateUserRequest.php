<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'email'      => "sometimes|email|unique:users,email,{$userId}",
            'phone'      => 'sometimes|string|max:20',
            'role'       => 'sometimes|in:user,admin',
        ];
    }
}
