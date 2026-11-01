<?php

declare(strict_types=1);

namespace App\Exception;

use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

/**
 * Stats the user already uploaded (API: 409); the app shares the same stats again at times, so
 * only a notice, not an error that is posted to the chats.
 */
#[WithLogLevel(LogLevel::NOTICE)]
final class StatsAlreadyAddedException extends \RuntimeException
{
}
