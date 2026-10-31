<?php

declare(strict_types=1);

namespace App\Command;

use App\Notifier\SlackActivity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;

/**
 * Posts a test message to both Slack channels: the activity one (SLACK_DSN) and SLACK_ERRORS_CHANNEL.
 */
#[AsCommand(name: 'app:slack:test', description: 'Post a test message to the Slack channels')]
final readonly class SlackTestCommand
{
    public function __construct(
        private SlackActivity $slackActivity,
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

        $text = \sprintf(':white_check_mark: AgentTools (%s) can post here.', $this->environment);

        try {
            $this->slackActivity->sendNow($text);
            $this->slackActivity->sendNow($text, $this->errorsChannel);
        } catch (TransportExceptionInterface|MessengerException $exception) {
            $io->error('Slack: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Posted to the activity channel and to #'.$this->errorsChannel.'.');

        return Command::SUCCESS;
    }
}
