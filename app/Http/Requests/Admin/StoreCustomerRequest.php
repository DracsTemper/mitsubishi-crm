<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'salesman_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Salesman->value),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50', Rule::in(Customer::STATUSES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'salesman_id.exists' => 'The selected owner must be an existing Salesman user.',
        ];
    }
}
