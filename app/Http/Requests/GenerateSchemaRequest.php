<?php

namespace App\Http\Requests;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSchemaRequest extends FormRequest 
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'prompt_text' => ['required', 'string', 'min:5'],
            'target_framework' => ['required', Rule::enum(TargetFramework::class)],
            'database_dialect' => ['required', Rule::enum(DatabaseDialect::class)],
            'target_version' => ['nullable', 'string'],
        ];
    }
}