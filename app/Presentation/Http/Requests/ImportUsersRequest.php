<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'mimetypes:text/csv,text/plain', 'max:65536'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'file' => [
                'description' => 'The CSV file containing users to import. Accepted formats: csv, txt. Max size: 64 MB.',
                'example' => null,
            ],
        ];
    }
}
