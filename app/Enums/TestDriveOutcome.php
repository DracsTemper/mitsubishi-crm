<?php

namespace App\Enums;

enum TestDriveOutcome: string
{
    case Booking = 'booking';
    case FollowUp = 'follow_up';
    case AnotherTestDrive = 'another_test_drive';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Interested — Proceed to Booking',
            self::FollowUp => 'Interested — Follow Up Later',
            self::AnotherTestDrive => 'Wants Another Test Drive',
            self::Lost => 'Not Interested / Lost',
        };
    }
}
