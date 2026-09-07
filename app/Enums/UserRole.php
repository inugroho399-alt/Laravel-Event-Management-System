<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';
    case Participant = 'participant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Organizer => 'Organizer',
            self::Participant => 'Participant',
        };
    }
}
