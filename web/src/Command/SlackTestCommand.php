<?php

declare(strict_types=1);

namespace App\Command;

use App\Notifier\ChatSender;
use App\Notifier\ChatText;
use Symfony\Component\Console\Attribute\AsCommand;
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
 * Checks the Slack setup: the bot token (Slack's auth.test names the bot and the workspace) and a
 * test message to each channel, SLACK_CHANNEL and SLACK_ERRORS_CHANNEL, with what
 * to do for Slack's usual errors; -v shows Slack's whole answer.
 */
#[AsCommand(name: 'app:slack:test', description: 'Check the Slack token and post a test message to the Slack channels')]
final readonly class SlackTestCommand
{
    use ChatTestHelpers;

    /** What to do for Slack's error codes; %bot% and %channel% are replaced. */
    private const array HINTS = [
        'not_in_channel' => 'The bot is not in #%channel%: in Slack, type /invite @%bot% in #%channel%. (Or give the app the scope chat:write.public and reinstall it, for public channels.)',
        'channel_not_found' => 'The bot sees no channel #%channel%: check the name (without #) or use the channel ID (C…, in the channel details); a private channel needs /invite @%bot% first.',
        'is_archived' => '#%channel% is archived: unarchive it or use another channel.',
        'invalid_auth' => 'Slack does not know the token: copy the Bot User OAuth Token (xoxb-…) from OAuth & Permissions into SLACK_DSN.',
        'not_authed' => 'SLACK_DSN has no token: slack://xoxb-…@default.',
        'token_revoked' => 'The token was revoked: reinstall the app and copy the new Bot User OAuth Token into SLACK_DSN.',
        'account_inactive' => 'The token was revoked: reinstall the app and copy the new Bot User OAuth Token into SLACK_DSN.',
        'missing_scope' => 'The app lacks the scope chat:write: add it under OAuth & Permissions → Bot Token Scopes and reinstall the app.',
    ];

    public function __construct(
        private ChatSender $chatSender,
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'SLACK_CHANNEL')]
        private string $channel,
        #[Autowire(env: 'SLACK_ERRORS_CHANNEL')]
        private string $errorsChannel,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
        #[Autowire(env: 'SLACK_DSN')]
        private string $dsn,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (\str_starts_with($this->dsn, 'null://')) {
            $io->warning('SLACK_DSN is null://null: nothing is posted. Set it in .env.local.');

            return Command::FAILURE;
        }

        $dsn = new Dsn($this->dsn);
        $token = (string) $dsn->getUser();
        $host = 'default' === $dsn->getHost() ? 'slack.com' : $dsn->getHost();

        $io->definitionList(
            ['Token' => $this->masked($token)],
            ['Host' => $host],
            ['Activity channel' => '#'.$this->channel],
            ['Errors channel' => '#'.$this->errorsChannel],
        );

        $bot = $this->authTest($io, $host, $token);
        if (null === $bot) {
            return Command::FAILURE;
        }

        $text = new ChatText(\sprintf('✅ AgentTools (%s) can post here.', $this->environment));
        $posted = true;
        foreach ([$this->channel, $this->errorsChannel] as $channel) {
            $posted = $this->post($io, $text, $channel, $bot) && $posted;
        }

        if (!$posted) {
            return Command::FAILURE;
        }

        $io->success('Posted to both channels.');

        return Command::SUCCESS;
    }

    /**
     * The bot's user name from Slack's auth.test, null if the token does not work.
     */
    private function authTest(SymfonyStyle $io, string $host, string $token): ?string
    {
        try {
            $result = $this->httpClient->request('POST', 'https://'.$host.'/api/auth.test', ['auth_bearer' => $token])->toArray(false);
        } catch (HttpException $httpException) {
            $io->error('Slack cannot be reached: '.$httpException->getMessage());

            return null;
        }

        $io->writeln('auth.test: '.\json_encode($result, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), SymfonyStyle::VERBOSITY_VERBOSE);

        if (true !== ($result['ok'] ?? false)) {
            $error = \is_string($result['error'] ?? null) ? $result['error'] : 'unknown';
            $io->error(\sprintf('Token check (auth.test): %s', $error));
            $this->hint($io, $error, '', '');

            return null;
        }

        $bot = \is_string($result['user'] ?? null) ? $result['user'] : '';
        $io->writeln(\sprintf(
            '<info>✔</info> Token works: bot <comment>@%s</comment> in workspace <comment>%s</comment> (%s)',
            $bot,
            \is_string($result['team'] ?? null) ? $result['team'] : '?',
            \is_string($result['url'] ?? null) ? $result['url'] : '?',
        ));

        return $bot;
    }

    private function post(SymfonyStyle $io, ChatText $text, string $channel, string $bot): bool
    {
        try {
            $this->chatSender->sendTo(ChatSender::SLACK, $channel, $text);
        } catch (TransportExceptionInterface|MessengerException $exception) {
            $cause = $this->cause($exception);
            $error = $this->slackError($cause);
            $io->writeln(\sprintf('<error>✘</error> #%s: %s', $channel, $error ?? $cause->getMessage()));
            if ($cause instanceof TransportException) {
                $io->writeln('  Slack answered: '.$cause->getResponse()->getContent(false), SymfonyStyle::VERBOSITY_VERBOSE);
            }

            $this->hint($io, $error, $channel, $bot);

            return false;
        }

        $io->writeln(\sprintf('<info>✔</info> #%s: posted', $channel));

        return true;
    }

    private function hint(SymfonyStyle $io, ?string $error, string $channel, string $bot): void
    {
        $hint = null === $error ? null : self::HINTS[$error] ?? null;
        if (null !== $hint) {
            $io->writeln('  → '.\strtr($hint, ['%channel%' => $channel, '%bot%' => '' === $bot ? 'YourBot' : $bot]));
        }
    }

    /** Slack's error code, e.g. not_in_channel. */
    private function slackError(\Throwable $exception): ?string
    {
        if (!$exception instanceof TransportException) {
            return null;
        }

        try {
            $error = $exception->getResponse()->toArray(false)['error'] ?? null;
        } catch (HttpException) {
            return null;
        }

        return \is_string($error) ? $error : null;
    }
}
