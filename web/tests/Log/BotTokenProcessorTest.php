<?php

declare(strict_types=1);

namespace App\Tests\Log;

use App\Log\BotTokenProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class BotTokenProcessorTest extends TestCase
{
    private const string URL = 'https://api.telegram.org/bot123:not-a-real_token/sendMessage';

    public function testMasksTheTokenInMessageContextAndExtra(): void
    {
        $exception = new \RuntimeException('Boom');
        $record = new BotTokenProcessor()(new LogRecord(
            new \DateTimeImmutable(),
            'http_client',
            Level::Info,
            'Request: "POST '.self::URL.'"',
            ['url' => self::URL, 'info' => ['url' => self::URL, 'http_code' => 200], 'exception' => $exception],
            ['uri' => self::URL],
        ));

        $masked = 'https://api.telegram.org/bot***/sendMessage';
        self::assertSame('Request: "POST '.$masked.'"', $record->message);
        self::assertSame(['url' => $masked, 'info' => ['url' => $masked, 'http_code' => 200], 'exception' => $exception], $record->context);
        self::assertSame(['uri' => $masked], $record->extra);
    }

    public function testKeepsOtherText(): void
    {
        self::assertSame('robot 12:30, bot: hi, https://slack.com/api/chat.postMessage', BotTokenProcessor::mask('robot 12:30, bot: hi, https://slack.com/api/chat.postMessage'));
    }
}
