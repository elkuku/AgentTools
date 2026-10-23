<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\AgentName;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * PUT /api/me/agent-name: sets the current user's agent name; 422 if another user has it.
 *
 * @implements ProcessorInterface<AgentName, AgentName>
 */
final readonly class AgentNameProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgentName
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The operation requires ROLE_AGENT.');
        }

        $agentName = \trim((string) $data->agentName);

        $owner = $this->userRepository->findOneByAgentName($agentName);
        if ($owner instanceof User && $owner !== $user) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(User::AGENT_NAME_TAKEN, null, [], $data, 'agentName', $agentName)]));
        }

        $user->setAgentName($agentName);
        $this->entityManager->flush();

        return new AgentName($agentName);
    }
}
