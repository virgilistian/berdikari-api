<?php

namespace Modules\IAM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', 'in:super-admin,business-owner,manager,supervisor,cashier,kitchen-staff,inventory-staff,finance,employee,viewer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'role.required' => 'Role wajib dipilih.',
            'role.in'       => 'Role tidak valid. Pilih salah satu dari: business-owner, manager, supervisor, cashier, kitchen-staff, inventory-staff, finance, employee, viewer.',
        ];
    }
}
