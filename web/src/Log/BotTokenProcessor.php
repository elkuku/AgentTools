<?php

declare(strict_types=1);

namespace App\Log;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Masks Telegram bot tokens in log records: Telegram's API has the token in the URL
 * (https://api.telegram.org/bot<token>/sendMessage), which the HTTP client logs. Masks the
 * message and every string in the context and extra, in every channel and handler, so neither
 * the log files nor the chat messages of ChatLogHandler show it.
 */
#[AsMonologProcessor]
final class BotTokenProcessor implements ProcessorInterface
{
    private const string TOKEN = '~\bbot\d+:[A-Za-z0-9_-]+~';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::mask($record->message),
            context: $this->maskAll($record->context),
            extra: $this->maskAll($record->extra),
        );
    }

    public static function mask(string $text): string
    {
        return \preg_replace(self::TOKEN, 'bot***', $text) ?? $text;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private function maskAll(array $values): array
    {
        \array_walk_recursive($values, static function (mixed &$value): void {
            if (\is_string($value)) {
                $value = self::mask($value);
            }
        });

        return $values;
    }
}
