<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';

    public function cssClass(): string
    {
        return match ($this) {
            self::USER => 'badge bg-secondary',
            self::ADMIN => 'badge bg-danger',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::USER => 'User',
        };
    }
}
