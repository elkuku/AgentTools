<?php

declare(strict_types=1);

namespace App\History;

use App\ApiResource\History;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * What a table row adds to the timeline when the user attended it: see TimelineEvent::$historyKey.
 */
final readonly class HistoryAttendance
{
    public function __construct(
        /** Identifies the row: stays the same when the document changes elsewhere. */
        #[Groups([History::READ])]
        public string $key,
        /** Y-m-d. */
        #[Groups([History::READ])]
        public string $date,
        /** The timeline event's text. */
        #[Groups([History::READ])]
        public string $text,
    ) {
    }

    /** The row's id on the page, to come back to after a change. */
    public function anchor(): string
    {
        return 'attend-'.\hash('xxh3', $this->key);
    }
}
