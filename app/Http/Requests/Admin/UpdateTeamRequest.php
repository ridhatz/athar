<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every field is optional so the screen can send just the toggle. Whether switching a team off
 * is allowed at all is ReferenceRules' call, not validation's.
 */
class UpdateTeamRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('teams', 'name')->ignore($this->route('team'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
