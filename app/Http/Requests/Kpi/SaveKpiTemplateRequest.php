<?php

namespace App\Http\Requests\Kpi;

use App\Enums\KpiDirection;
use App\Enums\KpiFrequency;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRuleFactory;

/**
 * Authorization is enforced inside the Action (KpiTemplatePolicy). One-template-per-team is the
 * Action's call too, so web and API cannot disagree about it.
 */
class SaveKpiTemplateRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|Rule>> */
    public function rules(): array
    {
        return [
            // Null means the fallback template, used by anyone whose team has none of its own.
            'team_id' => ['nullable', 'integer', ValidationRuleFactory::exists('teams', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.target_value' => ['required', 'numeric'],
            'items.*.direction' => ['required', ValidationRuleFactory::in(KpiDirection::values())],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'items.*.frequency' => ['nullable', ValidationRuleFactory::in(KpiFrequency::values())],
        ];
    }
}
