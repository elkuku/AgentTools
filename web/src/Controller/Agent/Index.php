<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The agents who show their profile to other users.
 */
#[Route('/agents', name: 'app_agent_index', methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Index extends BaseController
{
    public function __invoke(UserRepository $userRepository, AgentStatRepository $agentStatRepository): Response
    {
        $agents = $userRepository->findVisibleAgents();

        return $this->render('agent/index.html.twig', [
            'agents' => $agents,
            'latest' => $agentStatRepository->findLatestByOwners($agents),
        ]);
    }
}
