<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleCode;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleCode::values())],
            'team_ids' => ['sometimes', 'nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
            'locale' => ['sometimes', Rule::in(SetLocale::SUPPORTED)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
