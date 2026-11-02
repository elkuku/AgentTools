<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Form\StatsImportType;
use App\Import\CsvImportJob;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Starts the import of a CSV file of stats in parts (stats_import_controller.js): the form of
 * Import, answered with JSON: the import's id, its file's rows and where to import the next part
 * (ImportNext); 422 with the form's errors.
 */
#[Route('/agent-stats/import/start', name: 'app_agent_stat_import_start', methods: ['POST'])]
#[IsGranted(UserRole::USER->value)]
final class ImportStart extends BaseController
{
    public function __invoke(Request $request, CsvImportJob $job): JsonResponse
    {
        $form = $this->createForm(StatsImportType::class);
        $form->handleRequest($request);

        $file = $form->get('file')->getData();
        if (!$form->isSubmitted() || !$form->isValid() || !$file instanceof UploadedFile || StatsImportType::isMedals($file)) {
            return new JsonResponse(['errors' => $this->errors($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $import = $job->start($this->getUser(), $file);

        return new JsonResponse([
            'rows' => $import['rows'],
            'next' => $this->generateUrl('app_agent_stat_import_next', ['import' => $import['id']]),
        ]);
    }

    /**
     * @return list<string>
     */
    private function errors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            if ($error instanceof FormError) {
                $errors[] = $error->getMessage();
            }
        }

        return $errors;
    }
}
