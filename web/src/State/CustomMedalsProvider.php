<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomMedals;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<CustomMedals>
 */
final readonly class CustomMedalsProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CustomMedals
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return CustomMedals::fromArray($user->getCustomMedals());
    }
}
