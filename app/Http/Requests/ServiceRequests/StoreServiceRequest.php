<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\RequestPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorization is enforced inside the CreateRequest action, not here. */
class StoreServiceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', Rule::exists('request_categories', 'id')->where('is_active', true)],
            'deadline' => ['required', 'date', 'after:now'],
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ];
    }
}
