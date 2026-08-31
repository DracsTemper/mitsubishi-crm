<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class DealerVehicleAllocation extends Model
{
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = ['dealer_id', 'vehicle_id', 'quantity', 'status'];

    protected function casts(): array { return ['quantity' => 'integer']; }
    public function dealer(): BelongsTo { return $this->belongsTo(Dealer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function testDriveSlots(): HasMany { return $this->hasMany(TestDriveSlot::class); }

    protected static function booted(): void
    {
        static::saving(function (self $allocation): void {
            if ($allocation->quantity < 1 || ! in_array($allocation->status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Demo allocation requires a positive quantity and supported status.');
            }
        });
    }
}
