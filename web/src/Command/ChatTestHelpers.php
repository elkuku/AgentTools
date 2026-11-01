<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;

/**
 * What app:slack:test and app:telegram:test share.
 */
trait ChatTestHelpers
{
    /** The transport's exception, also when the message bus wrapped it. */
    private function cause(\Throwable $exception): \Throwable
    {
        if ($exception instanceof WrappedExceptionsInterface) {
            foreach ($exception->getWrappedExceptions(recursive: true) as $wrapped) {
                return $wrapped;
            }
        }

        return $exception;
    }

    private function masked(string $token): string
    {
        return \strlen($token) > 12 ? \substr($token, 0, 9).'…'.\substr($token, -4) : '(too short: '.\strlen($token).' characters)';
    }
}
