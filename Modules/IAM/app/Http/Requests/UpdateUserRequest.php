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
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'max:255'],
            'password' => ['sometimes', Password::min(8)],
            'role'     => ['sometimes', 'in:super-admin,business-owner,manager,supervisor,cashier,kitchen-staff,inventory-staff,finance,employee,viewer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string'  => 'Nama harus berupa teks.',
            'email.email'  => 'Format email tidak valid.',
            'role.in'      => 'Role tidak valid. Pilih salah satu dari: business-owner, manager, supervisor, cashier, kitchen-staff, inventory-staff, finance, employee, viewer.',
        ];
    }
}
