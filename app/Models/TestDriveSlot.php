<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TestDriveSlot extends Model
{
    protected $fillable = ['vehicle_id', 'dealer_vehicle_allocation_id', 'slot_date', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['slot_date' => 'date'];
    }

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function demoAllocation(): BelongsTo { return $this->belongsTo(DealerVehicleAllocation::class, 'dealer_vehicle_allocation_id'); }
    public function testDrive(): HasOne { return $this->hasOne(TestDrive::class, 'slot_id'); }

    public function getIsBookedAttribute(): bool
    {
        return $this->relationLoaded('testDrive') ? $this->testDrive !== null : $this->testDrive()->exists();
    }

    protected static function booted(): void
    {
        static::creating(function (self $slot): void {
            if (! $slot->dealer_vehicle_allocation_id && $slot->vehicle_id) {
                $vehicle = Vehicle::query()->find($slot->vehicle_id);
                $slot->dealer_vehicle_allocation_id = DealerVehicleAllocation::query()
                    ->where('vehicle_id', $slot->vehicle_id)
                    ->when($vehicle?->dealer_id, fn ($query, $dealerId) => $query->where('dealer_id', $dealerId))
                    ->value('id');
            }
        });
    }
}
