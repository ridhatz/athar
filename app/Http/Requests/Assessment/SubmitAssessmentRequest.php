<?php

namespace App\Http\Requests\Assessment;

use App\Support\AssessmentQuestions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The shape of a submission: exactly 24 answers, each a most/least pair of column indexes 0-3.
 *
 * Whether most and least are the SAME column on one item is deliberately not checked here — that
 * is a domain rule (you cannot call one trait both your most and least natural), and
 * AssessmentScoring is the one place it is enforced, the same way StoreKpiRequest leaves "is this
 * combination actually allowed" to the Action rather than duplicating it in validation.
 */
class SubmitAssessmentRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'size:'.AssessmentQuestions::COUNT],
            'answers.*.most' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
            'answers.*.least' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
        ];
    }
}
