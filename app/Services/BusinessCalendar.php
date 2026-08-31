<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class BusinessCalendar
{
    public function isOpen(Carbon|string $date): bool
    {
        return $this->closureReason($date) === null;
    }

    public function closureReason(Carbon|string $date): ?string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $holiday = config('business-calendar.holidays.'.$date->toDateString());

        if (is_string($holiday) && $holiday !== '') {
            return 'Holiday: '.$holiday;
        }

        return in_array($date->dayOfWeekIso, config('business-calendar.closed_weekdays', []), true)
            ? 'Weekly Holiday'
            : null;
    }

    /** @return list<array{0:string,1:string}> */
    public function slotTemplates(): array
    {
        return config('business-calendar.test_drive_slots', []);
    }

    public function endTimeFor(string $startTime): ?string
    {
        foreach ($this->slotTemplates() as [$start, $end]) {
            if (substr($start, 0, 5) === substr($startTime, 0, 5)) {
                return $end;
            }
        }

        return null;
    }

    public function nextOpenDate(Carbon|string|null $from = null): Carbon
    {
        $date = $from instanceof Carbon ? $from->copy() : Carbon::parse($from ?: today());
        while (! $this->isOpen($date)) {
            $date->addDay();
        }

        return $date->startOfDay();
    }
}
