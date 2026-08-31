<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\TestDriveOutcome;
use App\Enums\TestDriveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salesman\StoreTestDriveOutcomeRequest;
use App\Models\TestDrive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TestDriveOutcomeController extends Controller
{
    public function store(StoreTestDriveOutcomeRequest $request, string $testDrive): RedirectResponse
    {
        $testDrive = $this->ownedTestDrives($request)->with(['customer', 'booking'])->findOrFail($testDrive);
        if ($testDrive->status !== TestDriveStatus::Completed) {
            throw ValidationException::withMessages(['outcome' => 'A Customer decision can only be recorded after the Test Drive is completed.']);
        }
        if ($testDrive->outcome !== null) {
            throw ValidationException::withMessages(['outcome' => 'The Customer decision has already been recorded and cannot be overwritten.']);
        }

        $outcome = TestDriveOutcome::from($request->validated('outcome'));
        if ($outcome === TestDriveOutcome::Booking && $testDrive->booking) {
            throw ValidationException::withMessages(['outcome' => 'A Booking already exists for this Test Drive.']);
        }
        $testDrive->update([
            'outcome' => $outcome,
            'follow_up_date' => $outcome === TestDriveOutcome::FollowUp ? $request->validated('follow_up_date') : null,
            'outcome_notes' => in_array($outcome, [TestDriveOutcome::FollowUp, TestDriveOutcome::Lost], true) ? $request->validated('outcome_notes') : null,
            'loss_reason' => $outcome === TestDriveOutcome::Lost ? $request->validated('loss_reason') : null,
            'decided_at' => now(),
        ]);

        return match ($outcome) {
            TestDriveOutcome::Booking => redirect()->route('salesman.customers.test-drives.book.create', [$testDrive->customer, $testDrive])->with('success', 'Customer decision recorded. Complete the Booking details.'),
            TestDriveOutcome::AnotherTestDrive => redirect()->route('salesman.customers.test-drives.create', ['customer' => $testDrive->customer, 'from_test_drive' => $testDrive->id])->with('success', 'Customer decision recorded. Select a new Vehicle, date and Slot.'),
            default => redirect()->route('salesman.test-drives.show', $testDrive)->with('success', 'Customer decision recorded successfully.'),
        };
    }

    public function followUps(Request $request): View
    {
        $followUps = $this->ownedTestDrives($request)
            ->where('outcome', TestDriveOutcome::FollowUp->value)
            ->with(['customer', 'vehicle', 'salesman.dealer'])
            ->orderBy('follow_up_date')->paginate(15);

        return view('pages.salesman.follow-ups', compact('followUps'));
    }

    /** @return Builder<TestDrive> */
    private function ownedTestDrives(Request $request): Builder
    {
        return TestDrive::query()
            ->where('salesman_id', $request->user()->id)
            ->whereHas('customer', fn (Builder $query) => $query->where('salesman_id', $request->user()->id));
    }
}
