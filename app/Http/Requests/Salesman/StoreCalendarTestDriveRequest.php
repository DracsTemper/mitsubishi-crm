<?php

namespace App\Http\Requests\Salesman;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarTestDriveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'slot_id' => ['nullable', 'integer', 'exists:test_drive_slots,id', 'required_without:allocation_id'],
            'allocation_id' => ['nullable', 'integer', 'exists:dealer_vehicle_allocations,id', 'required_without:slot_id'],
            'slot_date' => ['nullable', 'date_format:Y-m-d', 'required_with:allocation_id'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:allocation_id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
