<?php

declare(strict_types=1);

namespace App\Log;

use App\Notifier\SlackActivity;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Posts errors and deprecations to SLACK_ERRORS_CHANNEL, one message per batch. Used in prod
 * behind Monolog's deduplication handlers (config/packages/monolog.yaml), which pass on whole
 * buffers: only records of level error and up and those of the deprecation channel are posted.
 * A failure to post goes to PHP's error log, not to Monolog, so it cannot come back here.
 */
final class SlackLogHandler extends AbstractHandler
{
    /** Slack cuts longer texts. */
    private const int MAX_LENGTH = 3000;

    private bool $sending = false;

    public function __construct(
        private readonly ChatterInterface $chatter,
        private readonly RequestStack $requestStack,
        #[Autowire(env: 'SLACK_ERRORS_CHANNEL')]
        private readonly string $channel,
    ) {
        parent::__construct(Level::Debug, false);
    }

    public function handle(LogRecord $record): bool
    {
        $this->handleBatch([$record]);

        return false;
    }

    /**
     * @param array<LogRecord> $records
     */
    public function handleBatch(array $records): void
    {
        $posted = \array_filter($records, $this->isPosted(...));
        if ([] === $posted || $this->sending) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        $text = \implode("\n", \array_map($this->line(...), $posted))
            ."\n".($request instanceof Request ? SlackActivity::escape($request->getMethod().' '.$request->getUri()) : 'console');

        $this->sending = true;
        try {
            $this->chatter->send(new ChatMessage(
                \mb_strlen($text) > self::MAX_LENGTH ? \mb_substr($text, 0, self::MAX_LENGTH).'…' : $text,
                new SlackOptions()->recipient($this->channel),
            ));
        } catch (TransportExceptionInterface|MessengerException $exception) {
            \error_log('Slack: a log message was not sent: '.$exception->getMessage());
        } finally {
            $this->sending = false;
        }
    }

    private function isPosted(LogRecord $record): bool
    {
        return $record->level->isHigherThan(Level::Warning) || 'deprecation' === $record->channel;
    }

    private function line(LogRecord $record): string
    {
        $line = \sprintf(
            '%s *%s* %s: %s',
            'deprecation' === $record->channel ? ':warning:' : ':rotating_light:',
            $record->level->getName(),
            $record->channel,
            SlackActivity::escape($record->message),
        );

        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof \Throwable) {
            $line .= \sprintf("\n`%s` at %s:%d", $exception::class, SlackActivity::escape($exception->getFile()), $exception->getLine());
        }

        return $line;
    }
}
