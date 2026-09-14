<?php

namespace Database\Seeders;

use Database\Seeders\ClientDemo\DevelopmentDemoVehicleAllocationSeeder;
use Database\Seeders\ClientDemo\DevelopmentTestDriveSlotSeeder;
use Database\Seeders\ClientDemo\DevelopmentVehicleSeeder;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Services\BusinessCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

/** Fictional presentation records. Never updates, deletes, or sends notifications. */
class ClientDemoSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentDemoSeeder::assertTarget();
        DB::transaction(function (): void {
            $this->call(DevelopmentDemoSeeder::class);
            $dealer = DevelopmentDemoFixtures::require(Dealer::class, ['code' => 'MDM-002']);
            foreach ([['dealer.dhanmondi@mitsubishi.test', UserRole::Dealer, 'Demo Manager - Mira Sen'],
                ['salesman.dhanmondi@mitsubishi.test', UserRole::Salesman, 'Demo Salesman - Nabil Roy']] as [$email, $role, $name]) {
                DevelopmentDemoFixtures::ensure(User::class, ['email' => $email], [
                    'name' => $name, 'role' => $role, 'dealer_id' => $dealer->id,
                    'password' => Hash::make(DevelopmentDataSeeder::PASSWORD),
                ]);
            }
            foreach (['Asha', 'Rafi', 'Tania'] as $name) {
                DevelopmentDemoFixtures::ensure(User::class, ['email' => 'customer.'.strtolower($name).'@example.test'], [
                    'name' => 'Demo Customer - '.$name, 'role' => UserRole::Customer, 'dealer_id' => null,
                    'password' => Hash::make(DevelopmentDataSeeder::PASSWORD),
                ]);
            }

            // Explicit fixture identities only; never attach fictional data to arbitrary users.
            $emails = ['salesman@mitsubishi.test', DevelopmentTestDriveSeeder::SALESMAN_EMAIL, 'salesman.dhanmondi@mitsubishi.test'];
            foreach (array_keys(DevelopmentDataSeeder::DEALERS) as $location) {
                for ($number = 1; $number <= 3; $number++) {
                    $emails[] = sprintf('salesman.%s.%02d@example.test', $location, $number);
                }
            }
            foreach ($emails as $ownerIndex => $email) {
                $owner = DevelopmentDemoFixtures::require(User::class, ['email' => $email]);
                // The declared model order is stable even when other allocations are added.
                $models = array_keys(DevelopmentDemoVehicleAllocationSeeder::PLAN[$owner->dealer->code] ?? []);
                $allocations = collect($models)->map(function (string $name) use ($owner) {
                    $vehicle = DevelopmentVehicleSeeder::vehicle($name);
                    return DevelopmentDemoFixtures::require(DealerVehicleAllocation::class, [
                        'dealer_id' => $owner->dealer_id, 'vehicle_id' => $vehicle->id, 'status' => 'active',
                    ]);
                });
                if ($allocations->isEmpty()) {
                    throw new LogicException('Demo salesman requires an active vehicle allocation.');
                }
                foreach (['pending', 'confirmed', 'cancelled', 'ready', 'follow_up', 'lost', 'scheduled', 'upcoming_confirmed'] as $index => $scenario) {
                    $this->scenario($owner, $ownerIndex, $index, $scenario, $allocations[$index % $allocations->count()]);
                }
            }
        });
    }

    private function scenario(User $owner, int $ownerIndex, int $index, string $scenario, DealerVehicleAllocation $allocation): void
    {
        $identity = sprintf('%02d-%02d', $ownerIndex + 1, $index + 1);
        $names = ['Asha Karim', 'Rafi Sen', 'Tania Rahman', 'Imran Das', 'Maya Roy', 'Arman Noor', 'Lina Haque', 'Sami Akter'];
        $bookingStatus = in_array($scenario, ['pending', 'confirmed', 'cancelled'], true) ? $scenario : null;
        $upcoming = in_array($scenario, ['scheduled', 'upcoming_confirmed'], true);
        $customer = DevelopmentDemoFixtures::ensure(Customer::class, ['email' => 'demo.client.'.$identity.'@example.test'], [
            'name' => $names[$index].' (Demo '.$identity.')', 'salesman_id' => $owner->id,
            'phone' => '000-DEMO-'.$identity, 'address' => 'Fictional showroom enquiry '.$identity,
            'city' => 'Dhaka (Demo)',
            'status' => match ($scenario) { 'confirmed' => 'reserved', 'lost', 'cancelled' => 'lost', 'pending' => 'hot', 'follow_up' => 'warm', default => 'active' },
        ]);
        $marker = '[CLIENT-DEMO:'.$identity.'] '.$scenario;
        $matches = TestDrive::where('notes', $marker)->get();
        if ($matches->count() > 1) {
            throw new LogicException('Duplicate client demo scenario: '.$marker);
        }
        $existing = $matches->first();
        $date = $existing?->scheduled_date->copy()
            ?? DevelopmentTestDriveSlotSeeder::workingDate($upcoming ? today()->addDays($index - 5) : today()->subDays(2 + $index * 3), $upcoming ? 1 : -1);
        $calendar = app(BusinessCalendar::class);
        if (! $calendar->isOpen($date) || $calendar->slotTemplates() === []) {
            throw new LogicException('Demo appointment date is closed or no slot templates are configured.');
        }
        $slot = $existing ? $existing->slot : $this->availableSlot($allocation, $owner, $date, $upcoming ? 1 : -1);
        if (! $slot || $slot->demoAllocation->dealer_id !== $owner->dealer_id
            || $slot->vehicle_id !== $allocation->vehicle_id
            || $calendar->endTimeFor($slot->start_time) !== substr($slot->end_time, 0, 5)) {
            throw new LogicException('Existing demo slot no longer matches its allocation or configured window.');
        }
        $date = $slot->slot_date;
        $outcome = $bookingStatus ? 'booking' : (in_array($scenario, ['follow_up', 'lost'], true) ? $scenario : null);
        $followUp = $existing?->follow_up_date ?? DevelopmentTestDriveSlotSeeder::workingDate(today()->addDays(4));
        if ($scenario === 'follow_up' && ! $calendar->isOpen($followUp)) {
            throw new LogicException('Existing demo follow-up date is now closed; no appointment was moved.');
        }
        $drive = DevelopmentDemoFixtures::ensure(TestDrive::class, ['notes' => $marker], [
            'customer_id' => $customer->id, 'salesman_id' => $owner->id, 'vehicle_id' => $allocation->vehicle_id,
            'slot_id' => $slot->id, 'scheduled_date' => $date->toDateString(), 'scheduled_time' => $slot->start_time,
            'status' => $upcoming ? ($scenario === 'scheduled' ? 'scheduled' : 'confirmed') : 'completed',
            'outcome' => $outcome, 'follow_up_date' => $scenario === 'follow_up' ? $followUp->toDateString() : null,
            'loss_reason' => $scenario === 'lost' ? 'not_ready' : null,
            'outcome_notes' => in_array($scenario, ['follow_up', 'lost'], true) ? 'Fictional scenario: customer is reviewing the purchase timeline.' : null,
            'decided_at' => $outcome ? $date->copy()->setTimeFromTimeString($slot->end_time)->toDateTimeString() : null,
        ]);
        if ($bookingStatus) {
            DevelopmentDemoFixtures::ensure(Booking::class, ['test_drive_id' => $drive->id], [
                'customer_id' => $customer->id, 'salesman_id' => $owner->id, 'vehicle_id' => $allocation->vehicle_id,
                'booking_date' => $date->toDateString(),
                'expected_delivery_date' => DevelopmentTestDriveSlotSeeder::workingDate($date->copy()->addDays(35))->toDateString(),
                'booking_amount' => $bookingStatus === 'cancelled' ? 0 : round((float) $allocation->vehicle->price * ($bookingStatus === 'confirmed' ? 0.2 : 0.1), 2),
                'status' => $bookingStatus, 'notes' => $marker.' Fictional booking; no actual payment or refund.',
            ]);
        }
    }

    private function availableSlot(DealerVehicleAllocation $allocation, User $owner, Carbon $date, int $direction): TestDriveSlot
    {
        $calendar = app(BusinessCalendar::class);
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $date = DevelopmentTestDriveSlotSeeder::workingDate($date, $direction);
            foreach ($calendar->slotTemplates() as [$start, $end]) {
                $identity = ['dealer_vehicle_allocation_id' => $allocation->id, 'slot_date' => $date->toDateString(),
                    'start_time' => $start.':00', 'end_time' => $end.':00'];
                $slot = TestDriveSlot::where('dealer_vehicle_allocation_id', $allocation->id)
                    ->whereDate('slot_date', $date)->where('start_time', $start.':00')->where('end_time', $end.':00')->first();
                $ownerBusy = TestDrive::where('salesman_id', $owner->id)->whereDate('scheduled_date', $date)
                    ->where('scheduled_time', '>=', $start.':00')->where('scheduled_time', '<', $end.':00')->exists();
                if (! $ownerBusy && (! $slot || ! $slot->testDrive()->exists())) {
                    return DevelopmentDemoFixtures::ensure(TestDriveSlot::class, $identity, ['vehicle_id' => $allocation->vehicle_id]);
                }
            }
            $date->addDays($direction);
        }
        throw new LogicException('No available demo slot within 60 working days.');
    }
}
