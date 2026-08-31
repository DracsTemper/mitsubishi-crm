<?php

namespace App\Models;

use App\Enums\TestDriveStatus;
use App\Enums\TestDriveOutcome;
use App\Enums\TestDriveLossReason;
use Database\Factories\TestDriveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class TestDrive extends Model
{
    /** @use HasFactory<TestDriveFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'salesman_id',
        'slot_id',
        'scheduled_date',
        'scheduled_time',
        'status',
        'outcome',
        'follow_up_date',
        'outcome_notes',
        'loss_reason',
        'decided_at',
        'notes',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TestDriveSlot::class, 'slot_id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'status' => TestDriveStatus::class,
            'outcome' => TestDriveOutcome::class,
            'loss_reason' => TestDriveLossReason::class,
            'follow_up_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TestDrive $testDrive): void {
            if ($testDrive->exists && $testDrive->getOriginal('outcome') !== null && $testDrive->isDirty('outcome')) {
                throw new InvalidArgumentException('A recorded Customer decision cannot be overwritten.');
            }
            if ($testDrive->customer_id && $testDrive->salesman_id) {
                $owned = Customer::query()
                    ->whereKey($testDrive->customer_id)
                    ->where('salesman_id', $testDrive->salesman_id)
                    ->exists();

                if (! $owned) {
                    throw new InvalidArgumentException('The Test Drive Customer must belong to its Salesman.');
                }
            }

            $status = $testDrive->status instanceof TestDriveStatus
                ? $testDrive->status->value
                : $testDrive->status;

            if (! in_array($status, TestDriveStatus::values(), true)) {
                throw new InvalidArgumentException('Unsupported Test Drive status.');
            }

            if ($testDrive->slot_id) {
                $slot = TestDriveSlot::query()->find($testDrive->slot_id);
                if (! $slot || $slot->vehicle_id !== (int) $testDrive->vehicle_id) {
                    throw new InvalidArgumentException('The selected Test Drive Slot must belong to its Vehicle.');
                }
            }
        });
    }
}
