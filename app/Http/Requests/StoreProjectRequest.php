<?php

namespace App\Http\Requests;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool 
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_name' => ['required', 'string', 'min:2', 'max:255'],
            'absolute_path' => ['required', 'string', 'max:2000'],
            'framework_type' => ['nullable', Rule::enum(TargetFramework::class)],
            'database_dialect' => ['nullable', Rule::enum(DatabaseDialect::class)],
            'confirm_generic_folder' => ['nullable', 'boolean'],
        ];
    }
}