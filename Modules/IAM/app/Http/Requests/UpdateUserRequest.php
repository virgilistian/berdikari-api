<?php

namespace Modules\IAM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', Password::min(8)],
            'role' => ['sometimes', 'in:owner,cashier,production'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Role tidak valid. Pilih: owner, cashier, atau production.',
        ];
    }
}
