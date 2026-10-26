<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomMedals;
use App\ApiResource\Me;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Me>
 */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Me
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || null === $user->id) {
            return null;
        }

        return new Me(
            $user->id,
            $user->getIdentifier(),
            $user->getRole()->value,
            $user->getAgentName(),
            CustomMedals::fromArray($user->getCustomMedals()),
            $user->getVisibleParts(),
            $user->getVisibilityChosenAt() instanceof \DateTimeImmutable,
            $user->getAvatar(),
        );
    }
}
