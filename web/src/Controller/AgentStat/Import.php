<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Form\StatsImportType;
use App\Import\AgentStatCsvImporter;
use App\Import\CsvImportResult;
use App\Import\CustomMedalsJsonImporter;
use App\Import\ImportFlashes;
use App\Notifier\ChatActivity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Imports the user's stats from a CSV file or custom medals from a JSON file, e.g. 4e-website's
 * exports; the result is shown on the user's stats. With JavaScript a CSV file is imported in
 * parts with a progress bar instead (ImportStart, ImportNext).
 */
#[Route('/agent-stats/import', name: 'app_agent_stat_import', methods: ['GET', 'POST'])]
#[IsGranted(UserRole::USER->value)]
final class Import extends BaseController
{
    public function __invoke(
        Request $request,
        AgentStatCsvImporter $importer,
        CustomMedalsJsonImporter $medalsImporter,
        ImportFlashes $flashes,
        ChatActivity $chatActivity,
    ): Response {
        $form = $this->createForm(StatsImportType::class);
        $form->handleRequest($request);

        $file = $form->get('file')->getData();

        // The form, again with what is wrong (render() answers 422 for an invalid one)
        if (!$form->isSubmitted() || !$form->isValid() || !$file instanceof UploadedFile) {
            return $this->render('agent_stat/import.html.twig', ['form' => $form]);
        }

        if (StatsImportType::isMedals($file)) {
            $flashes->medals($medalsImporter->import($this->getUser(), (string) \file_get_contents($file->getPathname())));
        } else {
            $result = $this->importStats($importer, $file);
            $chatActivity->statsImported($this->getUser(), $result->imported);
            $flashes->stats($result);
        }

        return $this->redirectToRoute('app_agent_stat_index', [], Response::HTTP_SEE_OTHER);
    }

    private function importStats(AgentStatCsvImporter $importer, UploadedFile $file): CsvImportResult
    {
        $csv = \fopen($file->getPathname(), 'r');
        if (false === $csv) {
            throw new \RuntimeException('The uploaded file cannot be read.');
        }

        try {
            return $importer->import($this->getUser(), $csv);
        } finally {
            \fclose($csv);
        }
    }
}
