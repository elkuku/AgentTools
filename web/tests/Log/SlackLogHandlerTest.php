<?php

declare(strict_types=1);

namespace App\Tests\Log;

use App\Log\SlackLogHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SlackLogHandlerTest extends TestCase
{
    /** @var list<ChatMessage> */
    private array $sent = [];

    private RequestStack $requestStack;

    private SlackLogHandler $handler;

    protected function setUp(): void
    {
        $chatter = $this->createStub(ChatterInterface::class);
        $chatter->method('send')->willReturnCallback(function (MessageInterface $message): ?SentMessage {
            self::assertInstanceOf(ChatMessage::class, $message);
            $this->sent[] = $message;

            return null;
        });
        $this->requestStack = new RequestStack();
        $this->handler = new SlackLogHandler($chatter, $this->requestStack, 'agenttools-errors');
    }

    public function testPostsErrorsAndDeprecationsOfABatchInOneMessage(): void
    {
        $this->requestStack->push(Request::create('https://agenttools.test/agent-stats?x=<1>'));

        $this->handler->handleBatch([
            $this->record(Level::Debug, 'Matched route'),
            $this->record(Level::Warning, 'Something odd'),
            $this->record(Level::Critical, 'Uncaught PHP Exception <RuntimeException>', 'request', new \RuntimeException('Boom')),
            $this->record(Level::Info, 'User Deprecated: old() is deprecated', 'deprecation'),
        ]);

        self::assertCount(1, $this->sent);
        self::assertSame('agenttools-errors', $this->sent[0]->getRecipientId());
        $lines = \explode("\n", $this->sent[0]->getSubject());
        self::assertSame(':rotating_light: *CRITICAL* request: Uncaught PHP Exception &lt;RuntimeException&gt;', $lines[0]);
        self::assertMatchesRegularExpression('/^`RuntimeException` at .+SlackLogHandlerTest\.php:\d+$/', $lines[1]);
        self::assertSame(':warning: *INFO* deprecation: User Deprecated: old() is deprecated', $lines[2]);
        self::assertSame('GET https://agenttools.test/agent-stats?x=%3C1%3E', $lines[3]);
        self::assertCount(4, $lines);
    }

    public function testPostsNothingBelowError(): void
    {
        $this->handler->handle($this->record(Level::Warning, 'Something odd'));

        self::assertSame([], $this->sent);
    }

    public function testNamesTheConsoleWithoutARequest(): void
    {
        $this->handler->handle($this->record(Level::Error, 'Command failed', 'console'));

        self::assertSame(":rotating_light: *ERROR* console: Command failed\nconsole", $this->sent[0]->getSubject());
    }

    public function testCutsLongMessages(): void
    {
        $this->handler->handle($this->record(Level::Error, \str_repeat('x', 5000)));

        self::assertSame(3001, \mb_strlen($this->sent[0]->getSubject()));
        self::assertStringEndsWith('…', $this->sent[0]->getSubject());
    }

    public function testAFailureDoesNotThrow(): void
    {
        $chatter = $this->createStub(ChatterInterface::class);
        $chatter->method('send')->willThrowException(new TransportException('Slack is down', $this->createStub(ResponseInterface::class)));
        $handler = new SlackLogHandler($chatter, new RequestStack(), 'agenttools-errors');

        $errorLog = \ini_set('error_log', '/dev/null');
        try {
            $handler->handle($this->record(Level::Error, 'Broken'));
        } finally {
            \ini_set('error_log', (string) $errorLog);
        }

        $this->addToAssertionCount(1);
    }

    private function record(Level $level, string $message, string $channel = 'app', ?\Throwable $exception = null): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(), $channel, $level, $message, $exception instanceof \Throwable ? ['exception' => $exception] : []);
    }
}
