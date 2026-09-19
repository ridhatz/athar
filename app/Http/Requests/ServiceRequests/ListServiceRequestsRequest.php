<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListServiceRequestsRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
            'category_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],
            'assigned_to_me' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'open' => ['nullable', 'boolean'],
            'my_teams' => ['nullable', 'boolean'],
            'due_today' => ['nullable', 'boolean'],
            'unrouted' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'deadline', 'priority'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
