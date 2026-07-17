<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:content.manage middleware guards the route
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['required', Rule::in(['community', 'program'])],
            'program_id' => [
                'required_if:page,program',
                'prohibited_if:page,community',
                'nullable',
                'exists:programs,id',
            ],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
