<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'erd_mermaid_text' => ['nullable', 'string'],
            'migration_files' => ['required', 'array'],
            'migration_files.*.filename' => ['required', 'string'],
            'migration_files.*.content' => ['required', 'string'],
        ];
    }
}
