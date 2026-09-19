<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\ReviewDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideReviewRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([ReviewDecision::Approved->value, ReviewDecision::RevisionRequested->value])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function decision(): ReviewDecision
    {
        return ReviewDecision::from($this->validated('decision'));
    }
}
