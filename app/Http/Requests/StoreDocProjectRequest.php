<?php

namespace App\Http\Requests;

use App\Enums\TargetFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_framework' => ['nullable', Rule::enum(TargetFramework::class)],
            'ai_model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
