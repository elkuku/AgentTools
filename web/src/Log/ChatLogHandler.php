<?php

declare(strict_types=1);

namespace App\Log;

use App\Notifier\ChatFeed;
use App\Notifier\ChatSender;
use App\Notifier\ChatText;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Posts errors and deprecations to Slack and Telegram (ChatFeed::Errors), one message per batch.
 * Used in dev and prod behind Monolog's deduplication handlers (config/packages/monolog.yaml),
 * which pass on whole buffers: only records of level error and up and those of the deprecation
 * channel are posted. A failure to post goes to PHP's error log, not to Monolog, so it cannot
 * come back here.
 */
final class ChatLogHandler extends AbstractHandler
{
    private bool $sending = false;

    public function __construct(
        private readonly ChatSender $chatSender,
        private readonly RequestStack $requestStack,
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

        $text = new ChatText();
        foreach ($posted as $record) {
            $this->line($text, $record);
        }

        $request = $this->requestStack->getMainRequest();
        $text->text($request instanceof Request ? $request->getMethod().' '.$request->getUri() : 'console');

        $this->sending = true;
        try {
            foreach ($this->chatSender->send(ChatFeed::Errors, $text) as $transport => $exception) {
                \error_log(\sprintf('%s: a log message was not sent: %s', $transport, $exception->getMessage()));
            }
        } finally {
            $this->sending = false;
        }
    }

    private function isPosted(LogRecord $record): bool
    {
        return $record->level->isHigherThan(Level::Warning) || 'deprecation' === $record->channel;
    }

    private function line(ChatText $text, LogRecord $record): void
    {
        $text->text('deprecation' === $record->channel ? '⚠️ ' : '🚨 ')
            ->bold($record->level->getName())
            ->text(\sprintf(' %s: %s', $record->channel, $record->message));

        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof \Throwable) {
            $text->text("\n")->code($exception::class)->text(\sprintf(' at %s:%d', $exception->getFile(), $exception->getLine()));
        }

        $text->text("\n");
    }
}
