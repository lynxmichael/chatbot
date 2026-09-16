<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],

            'category' => ['nullable', 'string', 'max:100'],

            'content' => ['required', 'string'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
