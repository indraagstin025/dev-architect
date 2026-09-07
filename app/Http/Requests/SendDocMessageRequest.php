<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendDocMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:8000'],
        ];
    }
}
