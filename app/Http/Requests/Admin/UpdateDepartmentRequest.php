<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Every field is optional so the screen can send just the toggle. */
class UpdateDepartmentRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($this->route('department'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
