<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The user\'s full name.',
                'example' => 'Jane Doe',
            ],
            'email' => [
                'description' => 'The user\'s email address. Must be unique.',
                'example' => 'jane@example.com',
            ],
            'password' => [
                'description' => 'The user\'s password. Minimum 8 characters.',
                'example' => 'secret123',
            ],
        ];
    }
}
