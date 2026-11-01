<?php

declare(strict_types=1);

namespace App\Tests\LinkList;

use App\LinkList\Link;
use App\LinkList\LinkListParser;
use App\LinkList\LinkSection;
use PHPUnit\Framework\TestCase;

class LinkListParserTest extends TestCase
{
    public function testReadsSectionsWithTheirLinks(): void
    {
        $list = new LinkListParser()->parse(<<<'MD'
            ## Official

            - [Ingress](https://ingress.com) — The official site.
            - [Intel *Map*](https://intel.ingress.com)

            ## Tools
            - [IITC-CE](https://iitc.app) - The community edition,
              with plugins.
            MD);

        self::assertEquals([
            new LinkSection('Official', [
                new Link('Ingress', 'https://ingress.com', 'The official site.'),
                new Link('Intel Map', 'https://intel.ingress.com'),
            ]),
            new LinkSection('Tools', [new Link('IITC-CE', 'https://iitc.app', 'The community edition, with plugins.')]),
        ], $list->sections);
        self::assertSame([], $list->problems);
    }

    public function testReadsOnlyTheListRegion(): void
    {
        $list = new LinkListParser()->parse(<<<'MD'
            ---
            title: Ingress links
            ---

            # Ingress links

            The intro, with a [link](https://example.com).

            <!-- #region list -->
            <!-- The format -->

            ## Official
            - [Ingress](https://ingress.com)

            <!-- #endregion list -->

            Text after the list.
            MD);

        self::assertEquals([new LinkSection('Official', [new Link('Ingress', 'https://ingress.com')])], $list->sections);
        self::assertSame([], $list->problems);
    }

    public function testLeavesOutAndReportsEntriesInAnotherFormat(): void
    {
        $list = new LinkListParser()->parse(<<<'MD'
            Text before the first section.

            ## Official
            - [Plain http](http://ingress.com)
            - No link
            - [Two](https://one.example) and [links](https://two.example)
            - Text first [Ingress](https://ingress.com)
            - [Ingress](https://ingress.com)

            A paragraph.
            MD);

        self::assertEquals([new LinkSection('Official', [new Link('Ingress', 'https://ingress.com')])], $list->sections);
        self::assertSame([
            'Line 1: only "## " sections with list entries are allowed',
            'Line 4: not a "- [Title](https://…) — Description" entry',
            'Line 5: not a "- [Title](https://…) — Description" entry',
            'Line 6: not a "- [Title](https://…) — Description" entry',
            'Line 7: not a "- [Title](https://…) — Description" entry',
            'Line 10: only "## " sections with list entries are allowed',
        ], $list->problems);
    }
}
