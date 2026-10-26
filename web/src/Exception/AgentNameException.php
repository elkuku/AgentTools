<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Agent stats for another agent than the user's agent name, or (setting the agent name) with a
 * nickname that is no agent name or another user's.
 */
final class AgentNameException extends \RuntimeException
{
}
