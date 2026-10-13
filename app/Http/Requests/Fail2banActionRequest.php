<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Fail2banActionRequest extends FormRequest
{
    /**
     * Tylko zalogowany admin może wykonać akcję.
     */
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'ip'    => 'nullable|ip',
            'jail'  => 'nullable|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'ip.ip'      => 'Nieprawidłowy format adresu IP.',
            'jail.regex' => 'Nazwa jaila może zawierać tylko litery, cyfry, myślniki i podkreślenia.',
        ];
    }
}