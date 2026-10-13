<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorization is enforced inside the Action (RequestCategoryPolicy). No `slug` here: the
 * Action derives it from the Indonesian name, so an admin can never make two slugs collide.
 */
class StoreCategoryRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'name_id' => ['required', 'string', 'max:255', Rule::unique('request_categories', 'name_id')],
            'name_en' => ['required', 'string', 'max:255'],
            'guidance_id' => ['nullable', 'string', 'max:2000'],
            'guidance_en' => ['nullable', 'string', 'max:2000'],
            // Column is an unsignedSmallInteger; left out, the Action puts it last.
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
