<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expire_date' => 'nullable|date',
            'name' => 'nullable|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'new_name' => 'nullable|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'username' => 'nullable|string|max:64',
            'group' => 'nullable|string|max:64',
        ];
    }
}