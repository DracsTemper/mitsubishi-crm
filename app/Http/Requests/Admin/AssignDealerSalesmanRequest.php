<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDealerSalesmanRequest extends FormRequest
{
    /** Keep validation feedback attached to the Salesman form. */
    protected $errorBag = 'assignSalesman';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Salesman->value),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected account must be an existing Salesman user.',
        ];
    }
}
