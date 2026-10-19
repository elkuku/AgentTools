<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Dto\AgentStatInput;
use App\Enum\UserRole;
use App\Exception\StatsAlreadyAddedException;
use App\Service\AgentStatService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/agent-stats', name: 'api_agent_stat_create', methods: ['POST'], format: 'json')]
#[IsGranted(UserRole::USER->value)]
final class AgentStatCreate extends BaseController
{
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')] AgentStatInput $input,
        AgentStatService $agentStatService,
    ): JsonResponse {
        try {
            $agentStat = $agentStatService->create($this->getUser(), $input);
        } catch (StatsAlreadyAddedException $statsAlreadyAddedException) {
            return $this->json(['error' => $statsAlreadyAddedException->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(
            [
                'id' => $agentStat->id,
                'createdAt' => $agentStat->createdAt->format(\DATE_ATOM),
            ],
            Response::HTTP_CREATED,
            [
                'Location' => $this->generateUrl(
                    'app_agent_stat_show',
                    ['id' => $agentStat->id],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        );
    }
}
