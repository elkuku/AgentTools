<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /api/agent-stats and /api/timeline-events list only the current user's stats and events.
 */
final readonly class AgentStatOwnerExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!\in_array($resourceClass, [AgentStat::class, TimelineEvent::class], true)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('owner');

        $queryBuilder
            ->andWhere(\sprintf('%s.owner = :%s', $rootAlias, $parameter))
            ->setParameter($parameter, $this->security->getUser());
    }
}
