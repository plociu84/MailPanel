<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Fail2banNoteRequest extends FormRequest
{
    /**
     * Tylko zalogowany admin może wykonać akcję.
     */
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * Reguły walidacji dla zapisu notatki.
     *
     * `ip` nie jest tu walidowane — przychodzi z URL (trasa ma regex).
     * Walidujemy tylko `notes`.
     */
    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'notes.string' => 'Notatka musi być tekstem.',
            'notes.max'    => 'Notatka może mieć maksymalnie 2000 znaków.',
        ];
    }

    public function attributes(): array
    {
        return [
            'notes' => 'notatka',
        ];
    }

    /**
     * Trim notatki przed walidacją.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => is_string($this->notes) ? trim($this->notes) : $this->notes,
        ]);
    }
}