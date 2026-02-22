<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'avatar' => [
                'description' => 'The avatar image file. Accepted formats: jpeg, png, gif, webp. Max size: 2 MB.',
                'example' => null,
            ],
        ];
    }
}
