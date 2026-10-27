<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Form\StatsImportType;
use App\Import\AgentStatCsvImporter;
use App\Import\CsvImportResult;
use App\Import\CustomMedalsImportResult;
use App\Import\CustomMedalsJsonImporter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Imports the user's stats from a CSV file or custom medals from a JSON file, e.g. 4e-website's
 * exports; the result is shown on the user's stats.
 */
#[Route('/agent-stats/import', name: 'app_agent_stat_import', methods: ['GET', 'POST'])]
#[IsGranted(UserRole::USER->value)]
final class Import extends BaseController
{
    /** At most this many rows that were not imported are listed. */
    private const int SHOWN_ERRORS = 10;

    public function __invoke(
        Request $request,
        AgentStatCsvImporter $importer,
        CustomMedalsJsonImporter $medalsImporter,
        TranslatorInterface $translator,
    ): Response {
        $form = $this->createForm(StatsImportType::class);
        $form->handleRequest($request);

        $file = $form->get('file')->getData();

        // The form, again with what is wrong (render() answers 422 for an invalid one)
        if (!$form->isSubmitted() || !$form->isValid() || !$file instanceof UploadedFile) {
            return $this->render('agent_stat/import.html.twig', ['form' => $form]);
        }

        if ('json' === \strtolower($file->getClientOriginalExtension())) {
            $this->medalsImported($medalsImporter->import($this->getUser(), (string) \file_get_contents($file->getPathname())), $translator);
        } else {
            $this->statsImported($this->importStats($importer, $file), $translator);
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

    private function statsImported(CsvImportResult $result, TranslatorInterface $translator): void
    {
        $this->addFlash($result->imported > 0 ? 'success' : 'info', $translator->trans('stats_import.done', [
            '%imported%' => $result->imported,
            '%skipped%' => $result->skipped,
        ]));
        $this->flashErrors($result->errors, $translator);
    }

    private function medalsImported(CustomMedalsImportResult $result, TranslatorInterface $translator): void
    {
        $this->addFlash($result->added + $result->upgraded > 0 ? 'success' : 'info', $translator->trans('stats_import.medals_done', [
            '%added%' => $result->added,
            '%upgraded%' => $result->upgraded,
            '%kept%' => $result->kept,
        ]));
        $this->flashErrors($result->errors, $translator);
    }

    /**
     * @param list<string> $errors what was not imported
     */
    private function flashErrors(array $errors, TranslatorInterface $translator): void
    {
        if ([] === $errors) {
            return;
        }

        // The texts come from the file: escaped, flashes are shown as HTML
        $lines = \array_map(\htmlspecialchars(...), \array_slice($errors, 0, self::SHOWN_ERRORS));
        $this->addFlash('warning', $translator->trans('stats_import.errors', ['%count%' => \count($errors)])
            .'<ul class="mb-0"><li>'.\implode('</li><li>', $lines).'</li></ul>');
    }
}
