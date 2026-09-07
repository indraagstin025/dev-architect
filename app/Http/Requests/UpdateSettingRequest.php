<?php

namespace App\Http\Requests;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'openrouter_api_key' => ['nullable', 'string', 'max:255'],
            'openrouter_model' => ['nullable', 'string', 'max:100'],
            'openrouter_base_url' => ['nullable', 'url', 'max:255'],
            'openrouter_model_docs' => ['nullable', 'string', 'max:100'],
            'openrouter_model_docs_fallback' => ['nullable', 'string', 'max:100'],
            'openrouter_api_key_docs' => ['nullable', 'string', 'max:255'],
            'openrouter_base_url_docs' => ['nullable', 'url', 'max:255'],
            'default_framework' => ['nullable', Rule::enum(TargetFramework::class)],
            'default_dialect' => ['nullable', Rule::enum(DatabaseDialect::class)],
        ];
    }
}
