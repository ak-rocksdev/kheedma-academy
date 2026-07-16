<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:content.manage middleware guards the route
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
