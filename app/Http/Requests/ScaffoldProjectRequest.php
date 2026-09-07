<?php

namespace App\Http\Requests;

use App\Services\ScaffoldProjectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScaffoldProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template' => ['required', 'string', Rule::in(ScaffoldProjectService::TEMPLATES)],
            'project_name' => ['required', 'string', 'max:61', 'regex:/^[a-z0-9][a-z0-9-_]{1,60}$/'],
            'parent_path' => ['required', 'string', 'max:2000'],
            'spring_group' => ['nullable', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/'],
            'spring_artifact' => ['nullable', 'string', 'max:61', 'regex:/^[a-z0-9][a-z0-9-_]{1,60}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_name.regex' => 'Nama proyek: huruf kecil, angka, strip/underscore, 2–61 karakter, diawali huruf/angka.',
        ];
    }
}
