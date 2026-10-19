<?php

declare(strict_types=1);

namespace App\Enum;

enum UploadSource: string
{
    case APP = 'app';
    case WEB = 'web';

    public function cssClass(): string
    {
        return match ($this) {
            self::APP => 'badge bg-success',
            self::WEB => 'badge bg-info',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::APP => 'App',
            self::WEB => 'Web',
        };
    }
}
