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
            'erd_mermaid_text' => ['nullable', 'string', 'max:50000'],
            'migration_files' => ['required', 'array', 'max:30'],
            'migration_files.*.filename' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
            'migration_files.*.content' => ['required', 'string', 'max:200000'],
        ];
    }
}
