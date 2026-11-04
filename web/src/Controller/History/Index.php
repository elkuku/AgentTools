<?php

declare(strict_types=1);

namespace App\Controller\History;

use App\Controller\BaseController;
use App\History\HistoryLoader;
use App\Repository\TimelineEventRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The XM Anomalies and Mission Days of past years from docs/events/, for everyone.
 */
#[Route('/history/{slug}', name: 'app_history', requirements: ['slug' => 'anomalies|mission-days'], defaults: ['slug' => 'anomalies'], methods: ['GET'])]
final class Index extends BaseController
{
    public function __invoke(string $slug, HistoryLoader $loader, TimelineEventRepository $events): Response
    {
        return $this->render('history/index.html.twig', [
            'document' => $loader->load($slug),
            'slug' => $slug,
            'slugs' => HistoryLoader::SLUGS,
            'attended' => $this->isGranted('ROLE_USER') ? \array_flip($events->findHistoryKeys($this->getUser())) : null,
            'today' => new \DateTimeImmutable()->format('Y-m-d'),
        ]);
    }
}
