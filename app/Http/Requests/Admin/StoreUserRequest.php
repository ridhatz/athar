<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleCode;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Authorization is enforced inside the Action (UserPolicy). Role/team coherence is UserRules. */
class StoreUserRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            // Optional: left empty, the system generates a strong one and shows it once.
            'password' => ['nullable', 'string', Password::defaults() ?? Password::min(8)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleCode::values())],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
            'locale' => ['nullable', Rule::in(SetLocale::SUPPORTED)],
        ];
    }
}
