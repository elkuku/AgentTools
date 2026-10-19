<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Enum\UserRole;
use App\Exception\InvalidStatsException;
use App\Exception\StatsAlreadyAddedException;
use App\Form\AgentStatImportType;
use App\Service\AgentStatService;
use App\Service\IngressStatsParser;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent-stats/new', name: 'app_agent_stat_new', methods: ['GET', 'POST'])]
#[IsGranted(UserRole::USER->value)]
final class Create extends BaseController
{
    public function __invoke(
        Request $request,
        IngressStatsParser $parser,
        AgentStatService $agentStatService,
    ): Response {
        $form = $this->createForm(AgentStatImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $agentStat = $this->import($form, $parser, $agentStatService);

            if ($agentStat instanceof AgentStat) {
                $this->addFlash('success', 'Agent stats have been imported.');

                return $this->redirectToRoute('app_agent_stat_show', ['id' => $agentStat->id], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('agent_stat/new.html.twig', [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Imports the pasted stats; on failure adds the errors to the form and returns null.
     *
     * @param FormInterface<mixed> $form
     */
    private function import(FormInterface $form, IngressStatsParser $parser, AgentStatService $agentStatService): ?AgentStat
    {
        $stats = $form->get('stats')->getData();

        try {
            return $agentStatService->create($this->getUser(), $parser->parse(\is_string($stats) ? $stats : ''));
        } catch (InvalidStatsException $invalidStatsException) {
            $errors = $invalidStatsException->errors;
        } catch (StatsAlreadyAddedException $statsAlreadyAddedException) {
            $errors = [$statsAlreadyAddedException->getMessage()];
        }

        foreach ($errors as $error) {
            $form->get('stats')->addError(new FormError($error));
        }

        return null;
    }
}
