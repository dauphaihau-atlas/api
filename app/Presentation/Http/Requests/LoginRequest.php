<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The user\'s email address.',
                'example' => 'admin@example.com',
            ],
            'password' => [
                'description' => 'The user\'s password.',
                'example' => 'secret123',
            ],
        ];
    }
}
