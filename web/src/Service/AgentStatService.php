<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Exception\AgentNameException;
use App\Exception\StatsAlreadyAddedException;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class AgentStatService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentStatRepository $agentStatRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /** See AgentStat's #[UniqueConstraint]. */
    private const string OWNER_DATETIME_CONSTRAINT = 'agent_stat_owner_datetime';

    /**
     * @throws AgentNameException         if the stats are for another agent, or the nickname is no agent name or taken
     * @throws StatsAlreadyAddedException if the owner already imported stats with this datetime
     */
    public function create(User $owner, AgentStatInput $input): AgentStat
    {
        $this->checkAgentName($owner, \trim($input->nickname));

        $agentStat = new AgentStat($owner, $input);

        if ($this->agentStatRepository->has($owner, $agentStat->datetime)) {
            throw new StatsAlreadyAddedException('Stat entry already added!');
        }

        $this->entityManager->persist($agentStat);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            // Another user took the agent name at the same moment
            if (!\str_contains($uniqueConstraintViolationException->getMessage(), self::OWNER_DATETIME_CONSTRAINT)) {
                throw new AgentNameException(User::AGENT_NAME_TAKEN, $uniqueConstraintViolationException->getCode(), previous: $uniqueConstraintViolationException);
            }

            // Same stats sent twice at the same moment
            throw new StatsAlreadyAddedException('Stat entry already added!', $uniqueConstraintViolationException->getCode(), previous: $uniqueConstraintViolationException);
        }

        return $agentStat;
    }

    /**
     * Without an agent name, the stats' nickname becomes the owner's agent name, unless another
     * user has it (ignoring case).
     *
     * @throws AgentNameException if the stats are for another agent, or the nickname is no agent name or taken
     */
    private function checkAgentName(User $owner, string $nickname): void
    {
        $agentName = $owner->getAgentName();
        if (null !== $agentName) {
            if (!$owner->isAgentName($nickname)) {
                throw new AgentNameException(\sprintf('These stats are for agent %s, your agent name is %s.', $nickname, $agentName));
            }

            return;
        }

        if (1 !== \preg_match(User::AGENT_NAME_PATTERN, $nickname)) {
            throw new AgentNameException(User::AGENT_NAME_INVALID);
        }

        $other = $this->userRepository->findOneByAgentName($nickname);
        if ($other instanceof User && $other !== $owner) {
            throw new AgentNameException(User::AGENT_NAME_TAKEN);
        }

        $owner->setAgentName($nickname);
    }
}
