<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\UserPrivacy;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;

/**
 * GET /api/users: every user, by agent name (users without one last, by identifier).
 *
 * @implements ProviderInterface<UserPrivacy>
 */
final readonly class UserPrivacyProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AgentStatRepository $agentStatRepository,
    ) {
    }

    /**
     * @return list<UserPrivacy>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $users = $this->userRepository->findAllByAgentName();
        $latest = $this->agentStatRepository->findLatestByOwners($users);

        return \array_values(\array_map(static function (User $user) use ($latest): UserPrivacy {
            $stat = $latest[(int) $user->id] ?? null;

            return new UserPrivacy(
                (int) $user->id,
                $user->getIdentifier(),
                $user->getRole()->value,
                $user->getAgentName(),
                $stat?->faction,
                $stat?->level,
                $user->getAvatar(),
                \array_map(static fn (ProfilePart $part): string => $part->value, $user->getVisibleParts()),
                $user->getVisibilityChosenAt() instanceof \DateTimeImmutable,
            );
        }, $users));
    }
}
