<?php

namespace App\Enums;

enum TestDriveStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
