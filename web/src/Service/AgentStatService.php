<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Exception\StatsAlreadyAddedException;
use App\Repository\AgentStatRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class AgentStatService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentStatRepository $agentStatRepository,
    ) {
    }

    /**
     * @throws StatsAlreadyAddedException if the owner already imported stats with this datetime
     */
    public function create(User $owner, AgentStatInput $input): AgentStat
    {
        $agentStat = new AgentStat($owner, $input);

        if ($this->agentStatRepository->has($owner, $agentStat->datetime)) {
            throw new StatsAlreadyAddedException('Stat entry already added!');
        }

        $this->entityManager->persist($agentStat);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            // Same stats sent twice at the same moment
            throw new StatsAlreadyAddedException('Stat entry already added!', $uniqueConstraintViolationException->getCode(), previous: $uniqueConstraintViolationException);
        }

        return $agentStat;
    }
}
