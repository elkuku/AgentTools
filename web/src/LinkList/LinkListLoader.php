<?php

declare(strict_types=1);

namespace App\LinkList;

use App\ApiResource\LinkList;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The link list of docs/links.md (the deploy has the whole repository), parsed once per change
 * of the file. Without the file the list is empty.
 */
final readonly class LinkListLoader
{
    public function __construct(
        private LinkListParser $parser,
        private CacheInterface $cache,
        #[Autowire('%kernel.project_dir%/../docs/links.md')]
        private string $file,
    ) {
    }

    public function load(): LinkList
    {
        $modified = @\filemtime($this->file);

        if (false === $modified) {
            return new LinkList();
        }

        return $this->cache->get(
            'link_list_'.\hash('xxh3', $this->file.'|'.$modified.'|'.\filesize($this->file)),
            fn (): LinkList => $this->parser->parse((string) \file_get_contents($this->file)),
        );
    }
}
