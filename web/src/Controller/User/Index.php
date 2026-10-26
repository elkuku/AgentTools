<?php

namespace App\Controller\User;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Every user, with the agent, the privacy settings and every picture (as the app's admin agents).
 */
#[Route('/admin/agents', name: 'app_user_index', methods: ['GET'])]
#[IsGranted(UserRole::ADMIN->value)]
final class Index extends BaseController
{
    public function __invoke(UserRepository $userRepository, AgentStatRepository $agentStatRepository): Response
    {
        $users = $userRepository->findAllByAgentName();

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'latest' => $agentStatRepository->findLatestByOwners($users),
        ]);
    }
}
