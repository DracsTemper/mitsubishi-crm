<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /** @var list<string> */
    public const STATUSES = [
        'new',
        'contacted',
        'active',
        'hot',
        'warm',
        'reserved',
        'purchased',
        'lost',
    ];

    /** @var list<string> */
    protected $fillable = [
        'salesman_id',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'status',
    ];

    /** @return BelongsTo<User, $this> */
    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
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

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $salesman = User::query()->find($customer->salesman_id);

            if ($salesman?->role !== UserRole::Salesman) {
                throw new InvalidArgumentException(
                    'A Customer owner must be an existing Salesman user.',
                );
            }
        });
    }
}
