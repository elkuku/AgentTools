<?php

declare(strict_types=1);

namespace App\Controller\Links;

use App\Controller\BaseController;
use App\LinkList\LinkListLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Ingress link list of docs/links.md, for everyone (as the app's Links screen).
 */
#[Route('/links', name: 'app_links', methods: ['GET'])]
final class Index extends BaseController
{
    public const string SUGGEST_URL = 'https://github.com/elkuku/AgentTools/edit/master/docs/links.md';

    public function __invoke(LinkListLoader $loader): Response
    {
        return $this->render('links/index.html.twig', [
            'linkList' => $loader->load(),
            'suggestUrl' => self::SUGGEST_URL,
        ]);
    }
}
