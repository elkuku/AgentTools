<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case USER = 'ROLE_USER';
    /** Uploads agent stats and custom medals from the app. */
    case AGENT = 'ROLE_AGENT';
    case ADMIN = 'ROLE_ADMIN';

    public function cssClass(): string
    {
        return match ($this) {
            self::USER => 'badge bg-secondary',
            self::AGENT => 'badge bg-success',
            self::ADMIN => 'badge bg-danger',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::AGENT => 'Agent',
            self::USER => 'User',
        };
    }
}
