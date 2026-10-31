<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SlackTestCommand;
use App\Notifier\SlackActivity;
use App\Repository\AgentStatRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SlackTestCommandTest extends TestCase
{
    private const string DSN = 'slack://xoxb-1234-5678-abcdefgh@default';

    /** @var array<string, array<string, mixed>> Slack's answer to chat.postMessage per channel */
    private array $answers = [];

    public function testPostsToBothChannels(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('xoxb-1234…efgh', $display);
        self::assertStringNotContainsString('abcdefgh', $display);
        self::assertStringContainsString('Token works: bot @agenttools in workspace Ingress EC (https://ingress-ec.slack.com/)', $display);
        self::assertStringContainsString('✔ #agenttools: posted', $display);
        self::assertStringContainsString('✔ #agenttools-errors: posted', $display);
    }

    public function testExplainsNotInChannel(): void
    {
        $this->answers['agenttools-errors'] = ['ok' => false, 'error' => 'not_in_channel'];
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('✔ #agenttools: posted', $display);
        self::assertStringContainsString('✘ #agenttools-errors: not_in_channel', $display);
        self::assertStringContainsString('type /invite @agenttools in #agenttools-errors', $display);
        self::assertStringContainsString('Slack answered: {"ok":false,"error":"not_in_channel"}', $display);
    }

    public function testExplainsABadToken(): void
    {
        $tester = $this->tester(['ok' => false, 'error' => 'invalid_auth']);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Token check (auth.test): invalid_auth', $display);
        self::assertStringContainsString('copy the Bot User OAuth Token', $display);
        self::assertStringNotContainsString('posted', $display);
    }

    public function testNeedsADsn(): void
    {
        $tester = $this->tester(dsn: 'null://null');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('SLACK_DSN is null://null', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $authTest Slack's answer to auth.test
     */
    private function tester(array $authTest = ['ok' => true, 'user' => 'agenttools', 'team' => 'Ingress EC', 'url' => 'https://ingress-ec.slack.com/'], string $dsn = self::DSN): CommandTester
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($authTest): ResponseInterface {
            if (\str_ends_with($url, '/api/auth.test')) {
                return new JsonMockResponse($authTest);
            }

            self::assertStringEndsWith('/api/chat.postMessage', $url);
            self::assertIsString($options['body'] ?? null);
            $channel = \json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR)['channel'];

            return new JsonMockResponse($this->answers[$channel] ?? ['ok' => true, 'channel' => 'C1', 'ts' => '1.2']);
        });

        $slackActivity = new SlackActivity(
            new Chatter(new SlackTransport('xoxb-1234-5678-abcdefgh', null, $httpClient)),
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(AgentStatRepository::class),
            new NullLogger(),
            'agenttools',
        );

        $application = new Application();
        $application->addCommand(new SlackTestCommand($slackActivity, $httpClient, 'agenttools', 'agenttools-errors', 'test', $dsn));

        return new CommandTester($application->find('app:slack:test'));
    }
}
