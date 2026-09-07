<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Attended = 'attended';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Attended => 'Attended',
        };
    }

    public function isValid(): bool
    {
        return $this !== self::Cancelled;
    }
}
