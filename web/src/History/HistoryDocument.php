<?php

declare(strict_types=1);

namespace App\History;

/**
 * A history document of docs/events/: its title and blocks.
 */
final readonly class HistoryDocument
{
    /**
     * @param list<HistoryBlock> $blocks
     * @param list<string>       $problems what the page cannot show, with its line
     */
    public function __construct(
        public string $title = '',
        public array $blocks = [],
        public array $problems = [],
    ) {
    }

    public function attendance(string $key): ?HistoryAttendance
    {
        foreach ($this->blocks as $block) {
            foreach ($block->attendance as $attendance) {
                if ($attendance?->key === $key) {
                    return $attendance;
                }
            }
        }

        return null;
    }

    /**
     * The years in the document, for the links at the top of the page.
     *
     * @return list<string>
     */
    public function years(): array
    {
        $years = [];

        foreach ($this->blocks as $block) {
            $text = $block->inlines[0]->text ?? '';

            if (HistoryBlock::HEADING === $block->type && 2 === $block->level && 1 === \preg_match('/^\d{4}$/', $text)) {
                $years[] = $text;
            }
        }

        return $years;
    }
}
