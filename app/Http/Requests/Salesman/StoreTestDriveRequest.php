<?php

namespace App\Http\Requests\Salesman;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestDriveRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('slot_choice')) {
            [$allocation, $date, $start] = array_pad(explode('|', (string) $this->input('slot_choice'), 3), 3, null);
            $this->merge(['allocation_id' => $allocation, 'slot_date' => $date, 'start_time' => $start]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slot_id' => ['nullable', 'integer', 'exists:test_drive_slots,id', 'required_without:allocation_id'],
            'allocation_id' => ['nullable', 'integer', 'exists:dealer_vehicle_allocations,id', 'required_without:slot_id'],
            'slot_date' => ['nullable', 'date_format:Y-m-d', 'required_with:allocation_id'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:allocation_id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
