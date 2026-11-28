<?php

declare(strict_types=1);

namespace App\IngressEvents;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Reads an event's details from ingress.com/news: the tasks of an article's campaigns and a season
 * overview's notes.
 */
final readonly class EventDetailParser
{
    /**
     * The tasks of each campaign under the article's "Campaign Details", as name => lines: its
     * stages, assignments and tasks.
     *
     * @return array<string, list<EventDetail>>
     */
    public function campaigns(HTMLDocument $document): array
    {
        $details = [];
        $inDetails = false;

        foreach ($document->querySelectorAll('main h3, main h4') as $heading) {
            if ('h3' === $heading->localName) {
                $inDetails = EventPageParser::CAMPAIGN_DETAILS === $this->text($heading);

                continue;
            }

            $list = $heading->nextElementSibling;

            if ($inDetails && 'ul' === $list?->localName) {
                $details[$this->text($heading)] = $this->detailList($list);
            }
        }

        return $details;
    }

    /**
     * A list's lines with the lists in them.
     *
     * @return list<EventDetail>
     */
    private function detailList(Element $list): array
    {
        $details = [];

        foreach ($list->children as $item) {
            if ('li' === $item->localName) {
                $details[] = $this->item($item);
            }
        }

        return $details;
    }

    /** A line: its text, and the lists in it as its lines. */
    private function item(Element $item): EventDetail
    {
        $text = '';
        $items = [];

        foreach ($item->childNodes as $node) {
            if ($node instanceof Element && \in_array($node->localName, ['ul', 'ol'], true)) {
                $items = [...$items, ...$this->detailList($node)];
            } else {
                $text .= $node->textContent;
            }
        }

        return new EventDetail(\trim((string) \preg_replace('/\s+/u', ' ', $text)), $items);
    }

    /**
     * An overview's notes on an event: the lines of the list after its heading but where and
     * when, as "Details: Complete a free Campaign …" and "Cygnus Tokens: …".
     *
     * @return list<EventDetail>
     */
    public function overview(?Element $list): array
    {
        return 'ul' === $list?->localName
            ? \array_values(\array_filter($this->detailList($list), static fn (EventDetail $detail): bool => 1 !== \preg_match('/^(When|Where):/', $detail->text)))
            : [];
    }

    private function text(Element $element): string
    {
        return \trim((string) \preg_replace('/\s+/u', ' ', (string) $element->textContent));
    }
}
