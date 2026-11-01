<?php

declare(strict_types=1);

namespace App\Notifier;

/**
 * A chat message's text with links and some formatting, written once and rendered for each chat:
 * Slack's mrkdwn and Telegram's HTML. Both cut the visible text, not the markup, so a cut never
 * breaks a link or a tag.
 */
final class ChatText
{
    private const array SLACK = ['text' => '%1$s', 'bold' => '*%1$s*', 'code' => '`%1$s`', 'link' => '<%2$s|%1$s>'];

    private const array TELEGRAM = ['text' => '%1$s', 'bold' => '<b>%1$s</b>', 'code' => '<code>%1$s</code>', 'link' => '<a href="%2$s">%1$s</a>'];

    /** @var list<array{'text'|'bold'|'code'|'link', string, string}> kind, text, URL */
    private array $parts = [];

    public function __construct(string $text = '')
    {
        $this->text($text);
    }

    public function text(string $text): self
    {
        return $this->add('text', $text);
    }

    public function bold(string $text): self
    {
        return $this->add('bold', $text);
    }

    public function code(string $text): self
    {
        return $this->add('code', $text);
    }

    public function link(string $label, string $url): self
    {
        return $this->add('link', $label, $url);
    }

    /** The visible text, e.g. for the log. */
    public function plain(): string
    {
        return \implode('', \array_map(static fn (array $part): string => $part[1], $this->parts));
    }

    public function forSlack(int $maxLength = 3000): string
    {
        return $this->render(self::SLACK, self::escapeSlack(...), $maxLength);
    }

    public function forTelegram(int $maxLength = 4000): string
    {
        return $this->render(self::TELEGRAM, static fn (string $text): string => \htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE), $maxLength);
    }

    /** Slack's control characters in a message's text. */
    public static function escapeSlack(string $text): string
    {
        return \strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }

    private function add(string $kind, string $text, string $url = ''): self
    {
        if ('' !== $text) {
            \assert(\in_array($kind, ['text', 'bold', 'code', 'link'], true));
            $this->parts[] = [$kind, $text, $url];
        }

        return $this;
    }

    /**
     * @param array<string, string>    $formats per kind, %1$s the text and %2$s the URL
     * @param callable(string): string $escape
     */
    private function render(array $formats, callable $escape, int $maxLength): string
    {
        $rendered = '';
        $left = $maxLength;
        foreach ($this->parts as [$kind, $text, $url]) {
            if ($left < 1) {
                return $rendered.'…';
            }

            $cut = \mb_strlen($text) > $left;
            if ($cut) {
                $text = \mb_substr($text, 0, $left);
            }

            $left -= \mb_strlen($text);
            $rendered .= \sprintf($formats[$kind], $escape($text), $escape($url));
            if ($cut) {
                return $rendered.'…';
            }
        }

        return $rendered;
    }
}
