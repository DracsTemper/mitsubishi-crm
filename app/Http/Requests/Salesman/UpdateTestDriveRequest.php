<?php

namespace App\Http\Requests\Salesman;

use App\Enums\TestDriveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestDriveRequest extends FormRequest
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
            'status' => ['required', Rule::enum(TestDriveStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
