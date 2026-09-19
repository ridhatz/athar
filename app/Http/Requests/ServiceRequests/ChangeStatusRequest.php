<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\RequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::enum(RequestStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toStatus(): RequestStatus
    {
        return RequestStatus::from($this->validated('to_status'));
    }
}
