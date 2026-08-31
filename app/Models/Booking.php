<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Booking extends Model
{
    protected $fillable = ['test_drive_id', 'customer_id', 'vehicle_id', 'salesman_id', 'booking_date', 'expected_delivery_date', 'booking_amount', 'total_paid', 'due_amount', 'status', 'notes'];

    public function testDrive(): BelongsTo { return $this->belongsTo(TestDrive::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function salesman(): BelongsTo { return $this->belongsTo(User::class, 'salesman_id'); }

    protected function casts(): array
    {
        return ['booking_date' => 'date', 'expected_delivery_date' => 'date', 'booking_amount' => 'decimal:2', 'total_paid' => 'decimal:2', 'due_amount' => 'decimal:2', 'status' => BookingStatus::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            $testDrive = TestDrive::query()->with(['customer.salesman', 'vehicle', 'slot.demoAllocation'])->find($booking->test_drive_id);
            $allocationDealerId = $testDrive?->slot?->demoAllocation?->dealer_id
                ?? $testDrive?->vehicle?->demoAllocations()->where('status', 'active')->where('dealer_id', $testDrive?->salesman?->dealer_id)->value('dealer_id');
            if (! $testDrive
                || $testDrive->customer_id !== (int) $booking->customer_id
                || $testDrive->vehicle_id !== (int) $booking->vehicle_id
                || $testDrive->salesman_id !== (int) $booking->salesman_id
                || $testDrive->customer->salesman->dealer_id !== $allocationDealerId) {
                throw new InvalidArgumentException('Booking ownership must match its Test Drive relationship.');
            }

            $bookingAmount = (float) $booking->booking_amount;
            $vehiclePrice = (float) $testDrive->vehicle->price;
            $booking->total_paid = number_format($bookingAmount, 2, '.', '');
            $booking->due_amount = number_format(max(0, $vehiclePrice - $bookingAmount), 2, '.', '');
        });
    }
}
