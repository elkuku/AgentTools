<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Repository\AgentStatRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent-stats', name: 'app_agent_stat_index', methods: ['GET'])]
#[IsGranted(UserRole::AGENT->value)]
final class Index extends BaseController
{
    public function __invoke(AgentStatRepository $agentStatRepository): Response
    {
        return $this->render('agent_stat/index.html.twig', [
            'agent_stats' => $agentStatRepository->findByOwner($this->getUser()),
        ]);
    }
}
