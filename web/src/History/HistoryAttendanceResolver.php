<?php

declare(strict_types=1);

namespace App\History;

/**
 * Finds the rows of a document that can be marked as attended: a site or Mission Day with one
 * exact date, under a year's heading. Ranges, unclear dates and events that did not take place
 * have none. The year comes from the "## 2024" heading, an Anomaly's series from the "### " below.
 */
final class HistoryAttendanceResolver
{
    private const string ANOMALY_SITE_COLUMN = 'Site';

    private const string MISSION_DAY_SITE_COLUMN = 'City';

    private const string NOT_HELD = '/cancelled|\(postponed\)|moved to/';

    public function resolve(HistoryDocument $document, string $slug): HistoryDocument
    {
        $year = null;
        $series = '';
        $blocks = [];

        foreach ($document->blocks as $block) {
            $year = $this->year($block, $year);
            $series = $this->series($block, $series);
            $blocks[] = HistoryBlock::TABLE === $block->type && null !== $year
                ? $block->withAttendance($this->table($block, $slug, $year, $series))
                : $block;
        }

        return new HistoryDocument($document->title, $blocks, $document->problems);
    }

    /** The year a "## " heading starts, null after another one. */
    private function year(HistoryBlock $block, ?string $year): ?string
    {
        if (HistoryBlock::HEADING !== $block->type || 2 !== $block->level) {
            return $year;
        }

        $text = $this->plain($block->inlines);

        return 1 === \preg_match('/^\d{4}$/', $text) ? $text : null;
    }

    private function series(HistoryBlock $block, string $series): string
    {
        return HistoryBlock::HEADING === $block->type && 3 === $block->level ? $this->plain($block->inlines) : $series;
    }

    /**
     * @return list<?HistoryAttendance> per row
     */
    private function table(HistoryBlock $block, string $slug, string $year, string $series): array
    {
        $column = $this->plain($block->headers[1] ?? []);

        if (self::ANOMALY_SITE_COLUMN !== $column && self::MISSION_DAY_SITE_COLUMN !== $column) {
            return [];
        }

        return \array_map(
            fn (array $row): ?HistoryAttendance => $this->row(
                $slug,
                $year,
                $this->plain($row[0] ?? []),
                $this->place($this->plain($row[1] ?? [])),
                $this->plain($row[2] ?? []),
                self::ANOMALY_SITE_COLUMN === $column ? \preg_replace('/\s*\(.*$/', '', $series).' Anomaly' : null,
            ),
            $block->rows,
        );
    }

    private function row(string $slug, string $year, string $day, string $place, string $note, ?string $anomaly): ?HistoryAttendance
    {
        $date = \DateTimeImmutable::createFromFormat('!j M Y', $day.' '.$year);

        if (false === $date || '' === $place || 1 === \preg_match(self::NOT_HELD, $note)) {
            return null;
        }

        $name = $anomaly ?? \preg_replace('/\s*\(.*$/', '', $note);
        $key = \sprintf('%s:%s:%s', $slug, $date->format('Y-m-d'), $place);

        return new HistoryAttendance($key, $date->format('Y-m-d'), $name.': '.$place);
    }

    /** The place without its flags. */
    private function place(string $cell): string
    {
        return \trim((string) \preg_replace('/^[\x{1F1E6}-\x{1F1FF}]+/u', '', $cell));
    }

    /**
     * @param list<HistoryInline> $inlines
     */
    private function plain(array $inlines): string
    {
        // A medal is an image: its name is not part of the text
        return \trim(\implode('', \array_map(static fn (HistoryInline $inline): string => null === $inline->medal ? $inline->text : '', $inlines)));
    }
}
