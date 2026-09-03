<?php

namespace App\Http\Requests;

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
            'project_name' => ['required', 'string', 'max:255'],
            'absolute_path' => ['required','string'],
            'framework_type' => ['nullable', Rule::enum(TargetFramework::class)],
        ];
    }
}