<?php

namespace App\Http\Requests\ServiceRequests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "user_id" present-but-null means "clear the PIC"; absent means "leave it".
 * The distinction is meaningful, so callers must only send the fields they intend.
 */
class AssignServiceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
