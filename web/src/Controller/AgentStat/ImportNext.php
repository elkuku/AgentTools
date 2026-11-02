<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Import\CsvImportJob;
use App\Import\ImportFlashes;
use App\Notifier\ChatActivity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Imports the next part of a CSV import (ImportStart): the rows done so far and of the file; once
 * all are done the result as flash messages and where to see it (the user's stats). 404 for an
 * import that is not the user's or is done.
 */
#[Route('/agent-stats/import/{import}', name: 'app_agent_stat_import_next', requirements: ['import' => '[0-9a-f]{32}'], methods: ['POST'])]
#[IsGranted(UserRole::USER->value)]
final class ImportNext extends BaseController
{
    public function __invoke(string $import, CsvImportJob $job, ImportFlashes $flashes, ChatActivity $chatActivity): JsonResponse
    {
        $next = $job->next($this->getUser(), $import);
        if (null === $next) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        if ($next['finished']) {
            $chatActivity->statsImported($this->getUser(), $next['result']->imported);
            $flashes->stats($next['result']);
        }

        return new JsonResponse([
            'done' => $next['done'],
            'rows' => $next['result']->rows,
            'imported' => $next['result']->imported,
            'finished' => $next['finished'],
            ...$next['finished'] ? ['redirect' => $this->generateUrl('app_agent_stat_index')] : [],
        ]);
    }
}
