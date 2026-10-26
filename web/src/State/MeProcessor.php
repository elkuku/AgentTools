<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\CustomMedals;
use App\ApiResource\Me;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * PATCH /api/me: sets the current user's agent name (422 if another user has it) and the groups
 * of custom medals sent; null keeps them.
 *
 * @implements ProcessorInterface<Me, Me>
 */
final readonly class MeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Me
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The operation requires ROLE_USER.');
        }

        if (null !== $data->agentName) {
            $agentName = \trim($data->agentName);

            $owner = $this->userRepository->findOneByAgentName($agentName);
            if ($owner instanceof User && $owner !== $user) {
                throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(User::AGENT_NAME_TAKEN, null, [], $data, 'agentName', $agentName)]));
            }

            $user->setAgentName($agentName);
            $data->agentName = $agentName;
        }

        if ($data->customMedals instanceof CustomMedals) {
            $user->setCustomMedals($data->customMedals->toArray());
        }

        $this->entityManager->flush();

        // What is stored, also for fields sent as null
        $data->agentName = $user->getAgentName();
        $data->customMedals = CustomMedals::fromArray($user->getCustomMedals());

        return $data;
    }
}
