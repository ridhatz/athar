<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorization is enforced inside the Action (TeamPolicy). */
class StoreTeamRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('teams', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
