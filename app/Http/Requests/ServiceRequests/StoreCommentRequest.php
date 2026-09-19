<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\AttachmentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:'.config('athar.attachments.max_kb')],
            'kind' => ['nullable', Rule::enum(AttachmentKind::class)],
        ];
    }

    public function kind(): ?AttachmentKind
    {
        return $this->filled('kind') ? AttachmentKind::from($this->input('kind')) : null;
    }
}
