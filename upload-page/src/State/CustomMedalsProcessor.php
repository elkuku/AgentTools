<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomMedals;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * PUT /api/me/custom-medals: replaces the current user's custom medals with the validated input.
 *
 * @implements ProcessorInterface<CustomMedals, CustomMedals>
 */
final readonly class CustomMedalsProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomMedals
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The operation requires ROLE_USER.');
        }

        $user->setCustomMedals($data->toArray());
        $this->entityManager->flush();

        return $data;
    }
}
