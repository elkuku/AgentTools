<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    /** Uploads agent stats and custom medals from the app. */
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';

    public function cssClass(): string
    {
        return match ($this) {
            self::USER => 'badge bg-secondary',
            self::ADMIN => 'badge bg-danger',
        };
    }

    /** A translation key (messages). */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'role.admin',
            self::USER => 'role.user',
        };
    }
}
