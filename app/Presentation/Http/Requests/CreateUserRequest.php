<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'exists:roles,slug'],
            'send_invite' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('send_invite') && ! $this->filled('password')) {
                    $validator->errors()->add('password', 'The password field is required unless send_invite is true.');
                }
            },
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
                'description' => 'The user\'s password. Minimum 8 characters. Required unless send_invite is true.',
                'example' => 'secret123',
            ],
            'role' => [
                'description' => 'Role slug to assign to the user.',
                'example' => 'user',
            ],
            'send_invite' => [
                'description' => 'Whether to email an invitation link so the user can set their own password.',
                'example' => true,
            ],
        ];
    }
}
