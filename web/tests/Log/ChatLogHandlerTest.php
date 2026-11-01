<?php

declare(strict_types=1);

namespace App\Tests\Log;

use App\Log\ChatLogHandler;
use App\Notifier\ChatSender;
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

class ChatLogHandlerTest extends TestCase
{
    /** @var array<string, ChatMessage> by transport */
    private array $sent = [];

    private RequestStack $requestStack;

    private ChatLogHandler $handler;

    protected function setUp(): void
    {
        $chatter = $this->createStub(ChatterInterface::class);
        $chatter->method('send')->willReturnCallback(function (MessageInterface $message): ?SentMessage {
            self::assertInstanceOf(ChatMessage::class, $message);
            $this->sent[(string) $message->getTransport()] = $message;

            return null;
        });
        $this->requestStack = new RequestStack();
        $this->handler = new ChatLogHandler(new ChatSender($chatter, 'agenttools', 'agenttools-errors', '-1001', '-1002'), $this->requestStack);
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

        self::assertSame([ChatSender::SLACK, ChatSender::TELEGRAM], \array_keys($this->sent));

        $slack = $this->sent[ChatSender::SLACK];
        self::assertSame('agenttools-errors', $slack->getRecipientId());
        $lines = \explode("\n", $slack->getSubject());
        self::assertSame('🚨 *CRITICAL* request: Uncaught PHP Exception &lt;RuntimeException&gt;', $lines[0]);
        self::assertMatchesRegularExpression('/^`RuntimeException` at .+ChatLogHandlerTest\.php:\d+$/', $lines[1]);
        self::assertSame('⚠️ *INFO* deprecation: User Deprecated: old() is deprecated', $lines[2]);
        self::assertSame('GET https://agenttools.test/agent-stats?x=%3C1%3E', $lines[3]);
        self::assertCount(4, $lines);

        $telegram = $this->sent[ChatSender::TELEGRAM];
        self::assertSame('-1002', $telegram->getRecipientId());
        $lines = \explode("\n", $telegram->getSubject());
        self::assertSame('🚨 <b>CRITICAL</b> request: Uncaught PHP Exception &lt;RuntimeException&gt;', $lines[0]);
        self::assertMatchesRegularExpression('/^<code>RuntimeException<\/code> at .+ChatLogHandlerTest\.php:\d+$/', $lines[1]);
        self::assertSame('⚠️ <b>INFO</b> deprecation: User Deprecated: old() is deprecated', $lines[2]);
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

        self::assertSame("🚨 *ERROR* console: Command failed\nconsole", $this->sent[ChatSender::SLACK]->getSubject());
    }

    public function testCutsLongMessages(): void
    {
        $this->handler->handle($this->record(Level::Error, \str_repeat('x', 5000)));

        // 3000 (Slack) and 4000 (Telegram) visible characters, the markup and …
        self::assertSame(3003, \mb_strlen($this->sent[ChatSender::SLACK]->getSubject()));
        self::assertStringEndsWith('x…', $this->sent[ChatSender::SLACK]->getSubject());
        self::assertSame(4008, \mb_strlen($this->sent[ChatSender::TELEGRAM]->getSubject()));
    }

    public function testAFailureDoesNotThrow(): void
    {
        $chatter = $this->createStub(ChatterInterface::class);
        $chatter->method('send')->willThrowException(new TransportException('Slack is down', $this->createStub(ResponseInterface::class)));
        $handler = new ChatLogHandler(new ChatSender($chatter, 'agenttools', 'agenttools-errors', '', ''), new RequestStack());

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
