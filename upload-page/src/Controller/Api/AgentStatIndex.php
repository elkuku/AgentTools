<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Enum\UserRole;
use App\Repository\AgentStatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The user's agent stats, newest first, in the format of POST /api/agent-stats (plus the id).
 */
#[Route('/api/agent-stats', name: 'api_agent_stat_index', methods: ['GET'], format: 'json')]
#[IsGranted(UserRole::USER->value)]
final class AgentStatIndex extends BaseController
{
    public function __invoke(AgentStatRepository $agentStatRepository): JsonResponse
    {
        return $this->json(\array_map(
            static fn (AgentStat $agentStat): array => [
                'id' => $agentStat->id,
                'datetime' => $agentStat->datetime->format(AgentStatInput::DATETIME_FORMAT),
                'nickname' => $agentStat->nickname,
                'faction' => $agentStat->faction,
            ] + $agentStat->getStats(),
            $agentStatRepository->findByOwner($this->getUser()),
        ));
    }
}
