<?php

declare(strict_types=1);

namespace App\Exception;

use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

/**
 * Agent stats for another agent than the user's agent name, or (setting the agent name) with a
 * nickname that is no agent name or another user's (API: 422). The user's mistake: a notice, not an
 * error that is posted to the chats.
 */
#[WithLogLevel(LogLevel::NOTICE)]
final class AgentNameException extends \RuntimeException
{
}
