<?php

declare(strict_types=1);

namespace App\Tests\Notifier;

use App\Notifier\ChatText;
use PHPUnit\Framework\TestCase;

class ChatTextTest extends TestCase
{
    public function testRendersForSlackAndTelegram(): void
    {
        $text = new ChatText('A <b> & ')->bold('bold')->text(' ')->code('Code')->text(' ')->link('Agent "<1>"', 'https://agenttools.test/a?b=1&c=2');

        self::assertSame('A &lt;b&gt; &amp; *bold* `Code` <https://agenttools.test/a?b=1&amp;c=2|Agent "&lt;1&gt;">', $text->forSlack());
        self::assertSame('A &lt;b&gt; &amp; <b>bold</b> <code>Code</code> <a href="https://agenttools.test/a?b=1&amp;c=2">Agent &quot;&lt;1&gt;&quot;</a>', $text->forTelegram());
        self::assertSame('A <b> & bold Code Agent "<1>"', $text->plain());
    }

    public function testCutsTheVisibleTextWithoutBreakingTheMarkup(): void
    {
        $text = new ChatText('1234')->link('567890', 'https://agenttools.test')->text('abc');

        self::assertSame('1234<a href="https://agenttools.test">56</a>…', $text->forTelegram(6));
        self::assertSame('1234<https://agenttools.test|567890>…', $text->forSlack(10));
        self::assertSame('1234<https://agenttools.test|567890>abc', $text->forSlack(13));
    }
}
