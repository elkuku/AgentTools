<?php

declare(strict_types=1);

namespace App\Tests\LinkList;

use App\LinkList\LinkListParser;
use PHPUnit\Framework\TestCase;

/**
 * docs/links.md, changed with pull requests: every entry in the format the website and the app read.
 */
class LinkListFileTest extends TestCase
{
    public function testTheLinkListIsInTheFormat(): void
    {
        $list = new LinkListParser()->parse((string) \file_get_contents(__DIR__.'/../../../docs/links.md'));

        self::assertSame([], $list->problems);
        self::assertNotEmpty($list->sections);

        $urls = [];
        foreach ($list->sections as $section) {
            self::assertNotEmpty($section->links, \sprintf('The section "%s" has no links', $section->title));

            foreach ($section->links as $link) {
                $urls[] = \rtrim($link->url, '/');
            }
        }

        self::assertSame(\array_unique($urls), $urls, 'A link is in the list twice');
    }
}
