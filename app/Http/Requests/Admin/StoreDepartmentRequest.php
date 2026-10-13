<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorization is enforced inside the Action (DepartmentPolicy). */
class StoreDepartmentRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')],
        ];
    }
}
