<?php

namespace App\Enums;

enum TestDriveLossReason: string
{
    case PriceTooHigh = 'price_too_high';
    case DislikedVehicle = 'disliked_vehicle';
    case PreferredAnotherModel = 'preferred_another_model';
    case BoughtCompetitor = 'bought_competitor';
    case FinancingIssue = 'financing_issue';
    case NotReady = 'not_ready';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PriceTooHigh => 'Price Too High',
            self::DislikedVehicle => 'Did Not Like the Vehicle',
            self::PreferredAnotherModel => 'Preferred Another Model',
            self::BoughtCompetitor => 'Bought Competitor Vehicle',
            self::FinancingIssue => 'Financing Issue',
            self::NotReady => 'Not Ready to Purchase',
            self::Other => 'Other',
        };
    }
}
