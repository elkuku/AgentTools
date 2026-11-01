<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramTestCommand;
use App\Notifier\ChatSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransport;
use Symfony\Component\Notifier\Chatter;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TelegramTestCommandTest extends TestCase
{
    private const string TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

    private const string DSN = 'telegram://'.self::TOKEN.'@default';

    /** Telegram's answer to getUpdates */
    private const array UPDATES = ['ok' => true, 'result' => [
        ['update_id' => 1, 'message' => ['message_id' => 1, 'chat' => ['id' => -1001234567890, 'type' => 'supergroup', 'title' => 'AgentTools uploads'], 'text' => '/start']],
        ['update_id' => 2, 'channel_post' => ['message_id' => 2, 'chat' => ['id' => -1009876543210, 'type' => 'channel', 'title' => 'AgentTools errors'], 'text' => 'hi']],
        ['update_id' => 3, 'message' => ['message_id' => 3, 'chat' => ['id' => -1001234567890, 'type' => 'supergroup', 'title' => 'AgentTools uploads'], 'text' => 'again']],
    ]];

    /** The chat whose sendMessage fails with "chat not found" */
    private ?string $missingChat = null;

    /** @var list<array<string, mixed>> what sendMessage got */
    private array $sent = [];

    public function testPostsToBothChats(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('123456789…Dsaw', $display);
        self::assertStringNotContainsString(self::TOKEN, $display);
        self::assertStringContainsString('Token works: bot @agenttools_bot', $display);
        self::assertStringContainsString('✔ -1001: posted', $display);
        self::assertStringContainsString('✔ -1002: posted', $display);
        self::assertStringNotContainsString('Chats the bot has seen', $display);
        self::assertSame(['-1001', '-1002'], \array_column($this->sent, 'chat_id'));
        self::assertSame('HTML', $this->sent[0]['parse_mode']);
        self::assertSame('✅ AgentTools (test) can post here.', $this->sent[0]['text']);
    }

    public function testListsTheChatsWithoutChatIds(): void
    {
        $tester = $this->tester(chat: '', errorsChat: '');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Chats the bot has seen', $display);
        self::assertMatchesRegularExpression('/-1001234567890\s+supergroup\s+AgentTools uploads/', $display);
        self::assertMatchesRegularExpression('/-1009876543210\s+channel\s+AgentTools errors/', $display);
        self::assertStringContainsString('Set TELEGRAM_CHAT_ID and TELEGRAM_ERRORS_CHAT_ID', $display);
        self::assertSame([], $this->sent);
    }

    public function testExplainsChatNotFound(): void
    {
        $this->missingChat = '-1002';
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('✔ -1001: posted', $display);
        self::assertStringContainsString('✘ -1002: Bad Request: chat not found', $display);
        self::assertStringContainsString('add @agenttools_bot to the group or channel', $display);
        self::assertStringContainsString('Telegram answered: {"ok":false,"error_code":400', $display);
    }

    public function testExplainsABadToken(): void
    {
        $tester = $this->tester(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized']);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Token check (getMe): Unauthorized', $display);
        self::assertStringContainsString('copy it from @BotFather', $display);
        self::assertStringNotContainsString('posted', $display);
    }

    public function testNeedsADsn(): void
    {
        $tester = $this->tester(dsn: 'null://null');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('TELEGRAM_DSN is null://null', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $getMe Telegram's answer to getMe
     */
    private function tester(array $getMe = ['ok' => true, 'result' => ['id' => 123456789, 'is_bot' => true, 'username' => 'agenttools_bot']], string $dsn = self::DSN, string $chat = '-1001', string $errorsChat = '-1002'): CommandTester
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($getMe): ResponseInterface {
            self::assertStringStartsWith('https://api.telegram.org/bot'.self::TOKEN.'/', $url);

            if (\str_ends_with($url, '/getMe')) {
                return new JsonMockResponse($getMe, ['http_code' => $getMe['error_code'] ?? 200]);
            }

            if (\str_ends_with($url, '/getUpdates')) {
                return new JsonMockResponse(self::UPDATES);
            }

            return $this->sendMessage($url, $options);
        });

        $chatSender = new ChatSender(new Chatter(new TelegramTransport(self::TOKEN, null, $httpClient)), '', '', $chat, $errorsChat);

        $application = new Application();
        $application->addCommand(new TelegramTestCommand($chatSender, $httpClient, $chat, $errorsChat, 'test', $dsn));

        return new CommandTester($application->find('app:telegram:test'));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sendMessage(string $url, array $options): ResponseInterface
    {
        self::assertStringEndsWith('/sendMessage', $url);
        self::assertIsString($options['body'] ?? null);
        /** @var array<string, mixed> $message */
        $message = \json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsString($message['chat_id']);
        if ($this->missingChat === $message['chat_id']) {
            return new JsonMockResponse(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], ['http_code' => 400]);
        }

        $this->sent[] = $message;

        return new JsonMockResponse(['ok' => true, 'result' => ['message_id' => \count($this->sent)]]);
    }
}
