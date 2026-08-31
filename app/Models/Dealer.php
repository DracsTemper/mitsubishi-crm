<?php

namespace App\Models;

use App\Enums\DealerStatus;
use Database\Factories\DealerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dealer extends Model
{
    /** @use HasFactory<DealerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'phone',
        'email',
        'address',
        'city',
        'status',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function demoVehicleAllocations(): HasMany
    {
        return $this->hasMany(DealerVehicleAllocation::class);
    }

    public function demoVehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'dealer_vehicle_allocations')->withPivot(['quantity', 'status'])->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DealerStatus::class,
        ];
    }
}
