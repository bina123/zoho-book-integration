<?php

namespace App\Http\Requests;

use App\Enums\AttachmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class DownloadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->route('type'),
            'id' => $this->route('id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(AttachmentType::class)],
            'id' => ['required', 'string', 'max:50'],
        ];
    }

    public function attachmentType(): AttachmentType
    {
        return AttachmentType::from((string) $this->validated('type'));
    }

    public function attachmentId(): string
    {
        return (string) $this->validated('id');
    }
}
