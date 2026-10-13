<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every field is optional so the screen can send just the toggle. `slug` is not accepted at all:
 * it is the stable handle fixed at creation. Whether the last active category may be switched
 * off is ReferenceRules' call, not validation's.
 */
class UpdateCategoryRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'name_id' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('request_categories', 'name_id')->ignore($this->route('category'))],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'guidance_id' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'guidance_en' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
