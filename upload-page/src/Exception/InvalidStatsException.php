<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Pasted text that is not valid Ingress all-time stats.
 */
final class InvalidStatsException extends \RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(\implode(' ', $errors));
    }
}
