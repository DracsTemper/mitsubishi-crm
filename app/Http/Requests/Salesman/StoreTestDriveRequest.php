<?php

namespace App\Http\Requests\Salesman;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestDriveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'integer', 'exists:test_drive_slots,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
