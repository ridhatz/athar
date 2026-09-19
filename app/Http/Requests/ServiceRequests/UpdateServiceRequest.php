<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\RequestPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Field-level authorization is enforced inside the UpdateRequest action. */
class UpdateServiceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('request_categories', 'id')->where('is_active', true)],
            'deadline' => ['sometimes', 'required', 'date'],
            'priority' => ['sometimes', 'required', Rule::enum(RequestPriority::class)],
        ];
    }
}
