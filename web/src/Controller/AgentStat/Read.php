<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Security\Voter\AgentStatVoter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent-stats/{id}', name: 'app_agent_stat_show', requirements: ['id' => '\d+'], methods: ['GET'])]
#[IsGranted(AgentStatVoter::VIEW, subject: 'agentStat')]
final class Read extends BaseController
{
    public function __invoke(AgentStat $agentStat): Response
    {
        return $this->render('agent_stat/show.html.twig', [
            'agent_stat' => $agentStat,
        ]);
    }
}
