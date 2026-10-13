<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Fail2banUnbanRequest extends FormRequest
{
    /**
     * Tylko zalogowany admin może wykonać akcję.
     */
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * Reguły walidacji dla odblokowania IP.
     *
     * `ip` i `jail` są WYMAGANE — inaczej `escapeshellarg(null)` rzuci
     * deprecation warning (PHP 8.1+) albo wyśle puste polecenie do SSH.
     */
    public function rules(): array
    {
        return [
            'ip'   => 'required|ip',
            'jail' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'ip.required'   => 'Adres IP jest wymagany.',
            'ip.ip'         => 'Nieprawidłowy format adresu IP.',
            'jail.required' => 'Nazwa jaila jest wymagana.',
            'jail.regex'    => 'Nazwa jaila może zawierać tylko litery, cyfry, myślniki i podkreślenia.',
            'jail.max'      => 'Nazwa jaila może mieć maksymalnie 64 znaki.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ip'   => 'adres IP',
            'jail' => 'jail',
        ];
    }

    /**
     * Trim wartości przed walidacją.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ip'   => is_string($this->ip)   ? trim($this->ip)   : $this->ip,
            'jail' => is_string($this->jail) ? trim($this->jail) : $this->jail,
        ]);
    }
}