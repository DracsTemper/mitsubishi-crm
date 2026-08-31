<?php

namespace App\Http\Requests\Salesman;

use App\Enums\TestDriveLossReason;
use App\Enums\TestDriveOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestDriveOutcomeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(TestDriveOutcome::class)],
            'follow_up_date' => [Rule::requiredIf($this->input('outcome') === TestDriveOutcome::FollowUp->value), 'nullable', 'date', 'after_or_equal:today'],
            'outcome_notes' => [Rule::requiredIf($this->input('outcome') === TestDriveOutcome::Lost->value && $this->input('loss_reason') === TestDriveLossReason::Other->value), 'nullable', 'string', 'max:2000'],
            'loss_reason' => [Rule::requiredIf($this->input('outcome') === TestDriveOutcome::Lost->value), 'nullable', Rule::enum(TestDriveLossReason::class)],
        ];
    }
}
