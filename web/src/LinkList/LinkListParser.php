<?php

declare(strict_types=1);

namespace App\LinkList;

use App\ApiResource\LinkList;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link as LinkNode;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Reads the link list of docs/links.md: the part between "<!-- #region list -->" and
 * "<!-- #endregion list -->" (the whole file without them), with "## " sections of
 * "- [Title](https://…) — Description" entries. Entries and text in another format are left out
 * and reported as problems (LinkListFileTest fails on them, so pull requests keep the format).
 */
final readonly class LinkListParser
{
    private const string REGION_START = '<!-- #region list -->';

    private const string REGION_END = '<!-- #endregion list -->';

    /** The characters between the link and the description. */
    private const string SEPARATORS = " \t-–—:";

    private MarkdownParser $parser;

    public function __construct()
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());

        $this->parser = new MarkdownParser($environment);
    }

    public function parse(string $markdown): LinkList
    {
        [$markdown, $lineOffset] = $this->region($markdown);

        $titles = [];
        /** @var list<list<Link>> $links per section */
        $links = [];
        $problems = [];

        foreach ($this->parser->parse($markdown)->children() as $block) {
            if ($block instanceof Heading && 2 === $block->getLevel()) {
                $titles[] = $this->text($block);
                $links[] = [];
            } elseif ([] !== $entries = $this->entries($block, $lineOffset, [] !== $titles, $problems)) {
                $links[\count($links) - 1] = [...$links[\count($links) - 1], ...$entries];
            }
        }

        return new LinkList(
            \array_map(static fn (string $title, array $sectionLinks): LinkSection => new LinkSection($title, $sectionLinks), $titles, $links),
            $problems,
        );
    }

    /**
     * The entries of a list in a section; other blocks are problems, except HTML comments.
     *
     * @param list<string> $problems
     *
     * @return list<Link>
     */
    private function entries(Node $block, int $lineOffset, bool $inSection, array &$problems): array
    {
        if ($block instanceof ListBlock && $inSection) {
            return $this->listEntries($block, $lineOffset, $problems);
        }

        if (!$block instanceof HtmlBlock) {
            $problems[] = \sprintf('Line %d: only "## " sections with list entries are allowed', $this->line($block, $lineOffset));
        }

        return [];
    }

    /**
     * @param list<string> $problems
     *
     * @return list<Link>
     */
    private function listEntries(ListBlock $list, int $lineOffset, array &$problems): array
    {
        $links = [];

        foreach ($list->children() as $item) {
            $link = $item instanceof ListItem ? $this->link($item) : null;

            if ($link instanceof Link) {
                $links[] = $link;
            } else {
                $problems[] = \sprintf('Line %d: not a "- [Title](https://…) — Description" entry', $this->line($item, $lineOffset));
            }
        }

        return $links;
    }

    /**
     * @return array{string, int} the list's Markdown and the lines before it
     */
    private function region(string $markdown): array
    {
        $start = \strpos($markdown, self::REGION_START);
        $end = \strpos($markdown, self::REGION_END);

        if (false === $start || false === $end || $end < $start) {
            return [$markdown, 0];
        }

        $before = \substr($markdown, 0, $start + \strlen(self::REGION_START));

        return [\substr($markdown, \strlen($before), $end - \strlen($before)), \substr_count($before, "\n")];
    }

    /**
     * An entry: one paragraph with one https link first and plain text after it.
     */
    private function link(ListItem $item): ?Link
    {
        $link = $this->firstLink($item);
        $description = $link instanceof LinkNode ? $this->description($link) : false;

        if (!$link instanceof LinkNode || false === $description || '' === $this->text($link)) {
            return null;
        }

        return new Link($this->text($link), $link->getUrl(), '' === $description ? null : $description);
    }

    /** The https link the entry starts with, null if it has more than one paragraph. */
    private function firstLink(ListItem $item): ?LinkNode
    {
        $paragraph = $item->firstChild();

        if (!$paragraph instanceof Paragraph || $paragraph->next() instanceof Node) {
            return null;
        }

        $first = $paragraph->firstChild();

        return $first instanceof LinkNode && \str_starts_with($first->getUrl(), 'https://') ? $first : null;
    }

    /** The text after the link without the separator, false with another link. */
    private function description(LinkNode $link): string|false
    {
        $description = '';

        for ($node = $link->next(); $node instanceof Node; $node = $node->next()) {
            if ($node instanceof LinkNode) {
                return false;
            }

            $description .= $this->text($node).' ';
        }

        return \trim(\ltrim((string) \preg_replace('/\s+/', ' ', $description), self::SEPARATORS));
    }

    /** The plain text of a node: formatting left out, line breaks as spaces. */
    private function text(Node $node): string
    {
        $text = '';

        foreach ($node->iterator() as $child) {
            if ($child instanceof StringContainerInterface) {
                $text .= $child->getLiteral();
            } elseif ($child instanceof Newline) {
                $text .= ' ';
            }
        }

        return \trim((string) \preg_replace('/\s+/', ' ', $text));
    }

    private function line(Node $node, int $lineOffset): int
    {
        return ($node instanceof AbstractBlock ? (int) $node->getStartLine() : 0) + $lineOffset;
    }
}
