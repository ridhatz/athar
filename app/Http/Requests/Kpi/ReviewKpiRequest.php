<?php

namespace App\Http\Requests\Kpi;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A reviewer's verdict. Whether a rejection needs a reason is the Action's call, not validation's,
 * so the rule lives in one place for web and API alike.
 */
class ReviewKpiRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|Rule>> */
    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
