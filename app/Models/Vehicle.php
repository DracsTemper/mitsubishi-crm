<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /** @var list<string> */
    public const STATUSES = ['available', 'reserved', 'sold', 'test_drive'];

    /** @var list<string> */
    protected $fillable = ['dealer_id', 'name', 'variant', 'model_year', 'color', 'price', 'description', 'image', 'status'];

    /** @return BelongsTo<Dealer, $this> */
    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    /** @return HasMany<TestDrive, $this> */
    public function testDrives(): HasMany
    {
        return $this->hasMany(TestDrive::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function testDriveSlots(): HasMany
    {
        return $this->hasMany(TestDriveSlot::class);
    }

    public function demoAllocations(): HasMany
    {
        return $this->hasMany(DealerVehicleAllocation::class);
    }

    public function demoDealers(): BelongsToMany
    {
        return $this->belongsToMany(Dealer::class, 'dealer_vehicle_allocations')->withPivot(['quantity', 'status'])->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['model_year' => 'integer', 'price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if (! in_array($vehicle->status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Unsupported Vehicle status.');
            }
        });
    }
}
