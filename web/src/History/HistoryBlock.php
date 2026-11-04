<?php

declare(strict_types=1);

namespace App\History;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\History;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A block of a document: a heading, a paragraph, a list or a table.
 */
final readonly class HistoryBlock
{
    public const string HEADING = 'heading';

    public const string PARAGRAPH = 'paragraph';

    public const string LIST = 'list';

    public const string TABLE = 'table';

    /**
     * @param list<HistoryInline>             $inlines    a heading's or paragraph's text
     * @param list<list<HistoryInline>>       $items      a list's entries
     * @param list<list<HistoryInline>>       $headers    a table's header cells
     * @param list<list<list<HistoryInline>>> $rows       a table's rows of cells
     * @param list<?HistoryAttendance>        $attendance per row, null for one that cannot be attended; empty for other tables
     */
    public function __construct(
        #[Groups([History::READ])]
        public string $type,
        #[Groups([History::READ])]
        public int $level = 0,
        #[Groups([History::READ])]
        #[ApiProperty(genId: false)]
        public array $inlines = [],
        #[Groups([History::READ])]
        #[ApiProperty(genId: false)]
        public array $items = [],
        #[Groups([History::READ])]
        #[ApiProperty(genId: false)]
        public array $headers = [],
        #[Groups([History::READ])]
        #[ApiProperty(genId: false)]
        public array $rows = [],
        #[Groups([History::READ])]
        #[ApiProperty(genId: false)]
        public array $attendance = [],
    ) {
    }

    /**
     * @param list<?HistoryAttendance> $attendance
     */
    public function withAttendance(array $attendance): self
    {
        return new self($this->type, $this->level, $this->inlines, $this->items, $this->headers, $this->rows, $attendance);
    }
}
