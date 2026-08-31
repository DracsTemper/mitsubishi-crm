<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Customer::STATUSES)],
            'dealer_id' => ['nullable', 'integer', Rule::exists('dealers', 'id')],
            'salesman_id' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id')->where('role', UserRole::Salesman->value),
            ],
        ];
    }
}
