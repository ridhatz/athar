<?php

namespace App\Http\Requests\Kpi;

use App\Enums\KpiDirection;
use App\Enums\KpiFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorization is enforced inside the Action (KpiPolicy). */
class StoreKpiRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            // A month, written as 2026-09; the Action pins it to the first of that month.
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'due_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.target_value' => ['required', 'numeric'],
            'items.*.direction' => ['required', Rule::in(KpiDirection::values())],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            // Weights are points out of 100; a single KPI can never be worth more than the sheet.
            'items.*.weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'items.*.frequency' => ['nullable', Rule::in(KpiFrequency::values())],
        ];
    }
}
