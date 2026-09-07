<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', 'string', Rule::in(['urd', 'prd', 'srs', 'sysdesign'])],
            'content_markdown' => ['required', 'string', 'min:1', 'max:200000'],
            'parent_version_id' => ['nullable', 'uuid', 'exists:doc_versions,id'],
        ];
    }
}
