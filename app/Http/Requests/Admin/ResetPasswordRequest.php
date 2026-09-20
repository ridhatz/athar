<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return ['password' => ['nullable', 'string', Password::defaults() ?? Password::min(8)]];
    }
}
