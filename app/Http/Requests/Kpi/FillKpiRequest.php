<?php

namespace App\Http\Requests\Kpi;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The achievements the employee reports, keyed by KPI item id.
 *
 * Nullable on purpose: clearing a box means "not reported yet", which is not the same as 0.
 */
class FillKpiRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|Rule>> */
    public function rules(): array
    {
        return [
            'achievements' => ['required', 'array'],
            'achievements.*' => ['nullable', 'numeric'],
        ];
    }
}
