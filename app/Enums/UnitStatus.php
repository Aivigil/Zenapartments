<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case Blocked = 'blocked';
    case Sold = 'sold';
    case PossessionTransferred = 'possession_transferred';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Blocked => 'Blocked',
            self::Sold => 'Sold',
            self::PossessionTransferred => 'Possession Transferred',
            self::Cancelled => 'Cancelled',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Blocked => 'yellow',
            self::Sold => 'blue',
            self::PossessionTransferred => 'purple',
            self::Cancelled => 'red',
        };
    }
}
