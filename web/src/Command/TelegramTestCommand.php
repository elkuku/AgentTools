<?php

declare(strict_types=1);

namespace App\Command;

use App\Notifier\ChatSender;
use App\Notifier\ChatText;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks the Telegram setup: the bot token (Telegram's getMe names the bot), the chats the bot
 * has seen messages from (getUpdates, to find the chat IDs) and a test message to each chat,
 * TELEGRAM_CHAT_ID and TELEGRAM_ERRORS_CHAT_ID, with what to do for Telegram's usual errors;
 * -v shows Telegram's whole answer.
 */
#[AsCommand(name: 'app:telegram:test', description: 'Check the Telegram token and post a test message to the Telegram chats')]
final readonly class TelegramTestCommand
{
    use ChatTestHelpers;

    /** What to do for Telegram's errors, by a part of its description; %bot% and %chat% are replaced. */
    private const array HINTS = [
        'Unauthorized' => 'Telegram does not know the token: copy it from @BotFather (/mybots → API Token) into TELEGRAM_DSN, telegram://BOT_TOKEN@default.',
        'chat not found' => "The bot sees no chat %chat%: add @%bot% to the group or channel, send a message there and run this command again to see its ID (a group's starts with -, a channel's or supergroup's with -100).",
        'bot was kicked' => '@%bot% was removed from %chat%: add it again.',
        'bot is not a member' => '@%bot% is not in %chat%: add it, in a channel as an administrator.',
        'not enough rights' => '@%bot% may not post in %chat%: in a channel make it an administrator with "Post messages", in a group allow members to send messages.',
        'need administrator rights' => '@%bot% may not post in %chat%: make it an administrator with "Post messages".',
        'upgraded to a supergroup' => "%chat% became a supergroup with a new ID: use the new ID (Telegram's answer, -v, has it as migrate_to_chat_id).",
    ];

    public function __construct(
        private ChatSender $chatSender,
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'TELEGRAM_CHAT_ID')]
        private string $chat,
        #[Autowire(env: 'TELEGRAM_ERRORS_CHAT_ID')]
        private string $errorsChat,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
        #[Autowire(env: 'TELEGRAM_DSN')]
        private string $dsn,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'List the chats the bot has seen messages from')]
        bool $chats = false,
    ): int {
        if (\str_starts_with($this->dsn, 'null://')) {
            $io->warning('TELEGRAM_DSN is null://null: nothing is posted. Set it in .env.local.');

            return Command::FAILURE;
        }

        $dsn = new Dsn($this->dsn);
        $token = $dsn->getUser().':'.$dsn->getPassword();
        $host = 'default' === $dsn->getHost() ? 'api.telegram.org' : $dsn->getHost();

        $this->showSetup($io, $token, $host);

        $bot = $this->getMe($io, $host, $token);
        if (null === $bot) {
            return Command::FAILURE;
        }

        $setChats = \array_filter([$this->chat, $this->errorsChat]);
        $complete = 2 === \count($setChats);
        if ($chats || !$complete) {
            $this->listChats($io, $host, $token, $bot);
        }

        $text = new ChatText(\sprintf('✅ AgentTools (%s) can post here.', $this->environment));
        $posted = \array_filter(\array_map(fn (string $chat): bool => $this->post($io, $text, $chat, $bot), $setChats));

        if (!$complete) {
            $io->warning('Set TELEGRAM_CHAT_ID and TELEGRAM_ERRORS_CHAT_ID in .env.local; a chat that is not set gets nothing.');
        }

        if (\count($posted) < 2) {
            return Command::FAILURE;
        }

        $io->success('Posted to both chats.');

        return Command::SUCCESS;
    }

    private function showSetup(SymfonyStyle $io, string $token, string $host): void
    {
        $io->definitionList(
            ['Token' => $this->masked($token)],
            ['Host' => $host],
            ['Activity chat' => $this->chat ?: '(not set)'],
            ['Errors chat' => $this->errorsChat ?: '(not set)'],
        );
    }

    /**
     * The bot's user name from Telegram's getMe, null if the token does not work.
     */
    private function getMe(SymfonyStyle $io, string $host, string $token): ?string
    {
        $result = $this->call($io, $host, $token, 'getMe');
        if (null === $result) {
            return null;
        }

        if (true !== ($result['ok'] ?? false)) {
            $description = $this->string($result['description'] ?? null);
            $io->error(\sprintf('Token check (getMe): %s', $description));
            $this->hint($io, $description, '', '');

            return null;
        }

        $bot = $this->string(\is_array($result['result'] ?? null) ? $result['result']['username'] ?? null : null);
        $io->writeln(\sprintf('<info>✔</info> Token works: bot <comment>@%s</comment>', $bot));

        return $bot;
    }

    /**
     * The chats in the bot's recent updates (Telegram keeps them for 24 hours).
     */
    private function listChats(SymfonyStyle $io, string $host, string $token, string $bot): void
    {
        $chats = $this->chats($this->call($io, $host, $token, 'getUpdates') ?? []);

        if ([] === $chats) {
            $io->writeln(\sprintf('The bot has seen no chats: add @%s to the group or channel, send a message there (in a group, /start@%s) and run this command again.', $bot, $bot));

            return;
        }

        $io->writeln('Chats the bot has seen:');
        $io->table(['Chat ID', 'Type', 'Name'], \array_values($chats));
    }

    /**
     * The chats of getUpdates' messages, channel posts and membership changes.
     *
     * @param array<mixed> $updates
     *
     * @return array<string, array{string, string, string}> ID, type and name by ID
     */
    private function chats(array $updates): array
    {
        $chats = [];
        foreach ($this->arrays($updates['result'] ?? null) as $update) {
            foreach ($this->arrays($update) as $part) {
                $chat = $this->chat($part['chat'] ?? null);
                if (null !== $chat) {
                    $chats[$chat[0]] = $chat;
                }
            }
        }

        return $chats;
    }

    /**
     * @return array{string, string, string}|null
     */
    private function chat(mixed $chat): ?array
    {
        $id = \is_array($chat) ? $chat['id'] ?? null : null;
        if (!\is_array($chat) || !\is_int($id)) {
            return null;
        }

        return [(string) $id, $this->string($chat['type'] ?? null), $this->string($chat['title'] ?? $chat['username'] ?? $chat['first_name'] ?? null)];
    }

    /**
     * @return array<array<mixed>>
     */
    private function arrays(mixed $value): array
    {
        return \is_array($value) ? \array_filter($value, \is_array(...)) : [];
    }

    private function string(mixed $value): string
    {
        return \is_string($value) ? $value : '?';
    }

    /**
     * @return array<mixed>|null Telegram's answer, null if it cannot be reached
     */
    private function call(SymfonyStyle $io, string $host, string $token, string $method): ?array
    {
        try {
            $result = $this->httpClient->request('GET', \sprintf('https://%s/bot%s/%s', $host, $token, $method))->toArray(false);
        } catch (HttpException $httpException) {
            $io->error('Telegram cannot be reached: '.\str_replace($token, $this->masked($token), $httpException->getMessage()));

            return null;
        }

        $io->writeln($method.': '.\json_encode($result, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), SymfonyStyle::VERBOSITY_VERBOSE);

        return $result;
    }

    private function post(SymfonyStyle $io, ChatText $text, string $chat, string $bot): bool
    {
        try {
            $this->chatSender->sendTo(ChatSender::TELEGRAM, $chat, $text);
        } catch (TransportExceptionInterface|MessengerException $exception) {
            $cause = $this->cause($exception);
            $description = $this->telegramError($cause);
            $io->writeln(\sprintf('<error>✘</error> %s: %s', $chat, $description ?? $cause->getMessage()));
            if ($cause instanceof TransportException) {
                $io->writeln('  Telegram answered: '.$cause->getResponse()->getContent(false), SymfonyStyle::VERBOSITY_VERBOSE);
            }

            $this->hint($io, $description, $chat, $bot);

            return false;
        }

        $io->writeln(\sprintf('<info>✔</info> %s: posted', $chat));

        return true;
    }

    private function hint(SymfonyStyle $io, ?string $description, string $chat, string $bot): void
    {
        $hints = \array_filter(self::HINTS, static fn (string $part): bool => \str_contains((string) $description, $part), \ARRAY_FILTER_USE_KEY);
        foreach (\array_slice($hints, 0, 1) as $hint) {
            $io->writeln('  → '.\strtr($hint, ['%chat%' => $chat, '%bot%' => '' === $bot ? 'YourBot' : $bot]));
        }
    }

    /** Telegram's description of the error, e.g. "Bad Request: chat not found". */
    private function telegramError(\Throwable $exception): ?string
    {
        if (!$exception instanceof TransportException) {
            return null;
        }

        try {
            $description = $exception->getResponse()->toArray(false)['description'] ?? null;
        } catch (HttpException) {
            return null;
        }

        return \is_string($description) ? $description : null;
    }
}
