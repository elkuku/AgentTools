<?php

declare(strict_types=1);

namespace App\History;

use App\ApiResource\History;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A run of text in a block: plain, bold, a link, or a medal image.
 */
final readonly class HistoryInline
{
    public function __construct(
        #[Groups([History::READ])]
        public string $text,
        #[Groups([History::READ])]
        public bool $bold = false,
        #[Groups([History::READ])]
        /** Only https links; a link between the documents stays plain text. */
        public ?string $url = null,
        #[Groups([History::READ])]
        /** The file name of a custom medal, without extension; $text is its name. */
        public ?string $medal = null,
    ) {
    }
}
