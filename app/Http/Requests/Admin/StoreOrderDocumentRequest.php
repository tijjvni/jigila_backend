<?php

namespace App\Http\Requests\Admin;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(DocumentType::values())],
            'note' => 'nullable|string|max:500',
            // 10 MB ceiling, and an explicit extension allowlist rather than a
            // mime wildcard — shipping paperwork is only ever a scan or a PDF.
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max'   => 'The document may not be larger than 10 MB.',
            'file.mimes' => 'Upload a PDF, image (JPG/PNG/WEBP) or Word document.',
        ];
    }
}
