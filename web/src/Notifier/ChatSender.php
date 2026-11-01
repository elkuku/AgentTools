<?php

declare(strict_types=1);

namespace App\Notifier;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Sends a ChatText to Slack and Telegram: to the feed's Slack channel (SLACK_CHANNEL,
 * SLACK_ERRORS_CHANNEL) and Telegram chat (TELEGRAM_CHAT_ID, TELEGRAM_ERRORS_CHAT_ID). A chat
 * whose channel or chat ID is empty is left out.
 */
final readonly class ChatSender
{
    public const string SLACK = 'slack';

    public const string TELEGRAM = 'telegram';

    public function __construct(
        private ChatterInterface $chatter,
        #[Autowire(env: 'SLACK_CHANNEL')]
        private string $slackChannel,
        #[Autowire(env: 'SLACK_ERRORS_CHANNEL')]
        private string $slackErrorsChannel,
        #[Autowire(env: 'TELEGRAM_CHAT_ID')]
        private string $telegramChat,
        #[Autowire(env: 'TELEGRAM_ERRORS_CHAT_ID')]
        private string $telegramErrorsChat,
    ) {
    }

    /**
     * The chats a feed goes to, with their channel or chat ID.
     *
     * @return array<self::SLACK|self::TELEGRAM, string>
     */
    public function recipients(ChatFeed $feed): array
    {
        return \array_filter(match ($feed) {
            ChatFeed::Activity => [self::SLACK => $this->slackChannel, self::TELEGRAM => $this->telegramChat],
            ChatFeed::Errors => [self::SLACK => $this->slackErrorsChannel, self::TELEGRAM => $this->telegramErrorsChat],
        }, static fn (string $recipient): bool => '' !== $recipient);
    }

    /**
     * Sends the text to every chat of the feed, also when one of them fails.
     *
     * @return array<self::SLACK|self::TELEGRAM, TransportExceptionInterface|MessengerException> the failures
     */
    public function send(ChatFeed $feed, ChatText $text): array
    {
        $failures = [];
        foreach ($this->recipients($feed) as $transport => $recipient) {
            try {
                $this->sendTo($transport, $recipient, $text);
            } catch (TransportExceptionInterface|MessengerException $exception) {
                $failures[$transport] = $exception;
            }
        }

        return $failures;
    }

    /**
     * @param self::SLACK|self::TELEGRAM $transport
     *
     * @throws TransportExceptionInterface|MessengerException
     */
    public function sendTo(string $transport, string $recipient, ChatText $text): void
    {
        $message = match ($transport) {
            self::SLACK => new ChatMessage($text->forSlack(), new SlackOptions()->recipient($recipient)),
            self::TELEGRAM => new ChatMessage($text->forTelegram(), new TelegramOptions()
                ->chatId($recipient)
                ->parseMode(TelegramOptions::PARSE_MODE_HTML)
                ->disableWebPagePreview(true)),
        };

        $this->chatter->send($message->transport($transport));
    }
}
