<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'dealer_id' => ['nullable', 'integer', Rule::exists('dealers', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'variant' => ['required', 'string', 'max:255'],
            'model_year' => ['required', 'integer', 'between:1900,'.(now()->year + 2)],
            'color' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Vehicle::STATUSES)],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
