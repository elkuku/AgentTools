<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Service\AgentStatService;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * POST /api/agent-stats: saves the validated input for the current user.
 * A StatsAlreadyAddedException is a 409 (see AgentStat's #[ApiResource]).
 *
 * @implements ProcessorInterface<AgentStatInput, AgentStat>
 */
final readonly class AgentStatProcessor implements ProcessorInterface
{
    public function __construct(
        private AgentStatService $agentStatService,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgentStat
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The operation requires ROLE_USER.');
        }

        return $this->agentStatService->create($user, $data);
    }
}
