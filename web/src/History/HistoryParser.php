<?php

declare(strict_types=1);

namespace App\History;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Reads a document of docs/events/ into blocks, which the website and the app show the same way.
 * Only what the documents use is understood; anything else is reported as a problem
 * (HistoryFileTest fails on it).
 */
final readonly class HistoryParser
{
    private MarkdownParser $parser;

    public function __construct()
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());

        $this->parser = new MarkdownParser($environment);
    }

    public function parse(string $markdown): HistoryDocument
    {
        $title = '';
        $blocks = [];
        $problems = [];

        foreach ($this->parser->parse($markdown)->children() as $node) {
            if ($node instanceof Heading && 1 === $node->getLevel()) {
                $title = $this->plain($node);

                continue;
            }

            $block = $this->block($node);

            if ($block instanceof HistoryBlock) {
                $blocks[] = $block;
            } else {
                $problems[] = $this->problem($node);
            }
        }

        return new HistoryDocument($title, $blocks, $problems);
    }

    private function problem(Node $node): string
    {
        return \sprintf('Line %d: not a heading, paragraph, list or table', $node instanceof AbstractBlock ? (int) $node->getStartLine() : 0);
    }

    private function block(Node $node): ?HistoryBlock
    {
        return match (true) {
            $node instanceof Heading => new HistoryBlock(HistoryBlock::HEADING, $node->getLevel(), $this->inlines($node)),
            $node instanceof Paragraph => new HistoryBlock(HistoryBlock::PARAGRAPH, inlines: $this->inlines($node)),
            $node instanceof ListBlock => new HistoryBlock(HistoryBlock::LIST, items: $this->items($node)),
            $node instanceof Table => $this->table($node),
            default => null,
        };
    }

    /**
     * @return list<list<HistoryInline>>
     */
    private function items(ListBlock $list): array
    {
        $items = [];

        foreach ($list->children() as $item) {
            $paragraph = $item->firstChild();
            $items[] = $paragraph instanceof Node ? $this->inlines($paragraph) : [];
        }

        return $items;
    }

    private function table(Table $table): HistoryBlock
    {
        return new HistoryBlock(HistoryBlock::TABLE, headers: $this->rows($table, true)[0] ?? [], rows: $this->rows($table, false));
    }

    /**
     * @return list<list<list<HistoryInline>>>
     */
    private function rows(Table $table, bool $head): array
    {
        $rows = [];

        foreach ($table->children() as $section) {
            if ($section instanceof TableSection && $section->isHead() === $head) {
                foreach ($section->children() as $row) {
                    $rows[] = $this->cells($row);
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<list<HistoryInline>>
     */
    private function cells(Node $row): array
    {
        $cells = [];

        if ($row instanceof TableRow) {
            foreach ($row->children() as $cell) {
                if ($cell instanceof TableCell) {
                    $cells[] = $this->inlines($cell);
                }
            }
        }

        return $cells;
    }

    /**
     * @return list<HistoryInline>
     */
    private function inlines(Node $parent, bool $bold = false): array
    {
        $inlines = [];

        foreach ($parent->children() as $node) {
            $inlines = [...$inlines, ...$this->inline($node, $bold)];
        }

        return $inlines;
    }

    /**
     * @return list<HistoryInline>
     */
    private function inline(Node $node, bool $bold): array
    {
        return match (true) {
            $node instanceof Strong => $this->inlines($node, true),
            $node instanceof Image => [new HistoryInline($this->plain($node), medal: $this->medal($node->getUrl()))],
            $node instanceof Link => [new HistoryInline($this->plain($node), $bold, \str_starts_with($node->getUrl(), 'https://') ? $node->getUrl() : null)],
            $node instanceof Newline => [new HistoryInline(' ', $bold)],
            $node instanceof StringContainerInterface => [new HistoryInline($node->getLiteral(), $bold)],
            default => $this->inlines($node, $bold),
        };
    }

    /** The medal of an image, which the documents link in the app's assets. */
    private function medal(string $url): ?string
    {
        return 1 === \preg_match('~/custom_medals/([a-z0-9_]+)\.webp$~', $url, $file) ? $file[1] : null;
    }

    private function plain(Node $node): string
    {
        $text = '';

        foreach ($node->iterator() as $child) {
            if ($child instanceof StringContainerInterface) {
                $text .= $child->getLiteral();
            }
        }

        return \trim($text);
    }
}
