<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Agent stats for another agent than the user's agent name, or before the user set one.
 */
final class AgentNameException extends \RuntimeException
{
}
