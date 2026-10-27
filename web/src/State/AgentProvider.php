<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Agent;
use App\ApiResource\CustomMedals;
use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\AgentStatRepository;
use App\Repository\TimelineEventRepository;
use App\Repository\UserRepository;

/**
 * GET /api/agents and /api/agents/{agentName}: the agents who show the agent part, with what
 * they show (as web's Agent controllers).
 *
 * @implements ProviderInterface<Agent>
 */
final readonly class AgentProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AgentStatRepository $agentStatRepository,
        private TimelineEventRepository $timelineEventRepository,
    ) {
    }

    /**
     * @return list<Agent>|Agent|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Agent|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $users = $this->userRepository->findVisibleAgents();
            $latest = $this->agentStatRepository->findLatestByOwners($users);

            return \array_values(\array_map(fn (User $user): Agent => $this->agent($user, $latest[(int) $user->id] ?? null), $users));
        }

        $user = $this->userRepository->findOneByAgentName((string) ($uriVariables['agentName'] ?? ''));
        if (!$user instanceof User || !$user->isVisible(ProfilePart::AGENT)) {
            return null;
        }

        $stats = $this->agentStatRepository->findByOwner($user);
        $showHistory = $user->isVisible(ProfilePart::STATS_HISTORY);

        return $this->agent(
            $user,
            $stats[0] ?? null,
            $user->isVisible(ProfilePart::LATEST_STATS) && isset($stats[0]) ? $this->row($stats[0]) : null,
            $showHistory ? \array_map($this->row(...), $stats) : null,
            $showHistory ? \array_map(
                static fn (TimelineEvent $event): array => [
                    'date' => $event->date?->format(TimelineEvent::DATE_FORMAT) ?? '',
                    'endDate' => $event->endDate?->format(TimelineEvent::DATE_FORMAT),
                    'text' => $event->text,
                ],
                $this->timelineEventRepository->findByOwner($user),
            ) : null,
            $user->isVisible(ProfilePart::CUSTOM_MEDALS) ? CustomMedals::fromArray($user->getCustomMedals()) : null,
        );
    }

    /**
     * @param array<string, int|string|null>|null                                $latestStats
     * @param list<array<string, int|string|null>>|null                          $history
     * @param list<array{date: string, endDate: string|null, text: string}>|null $events
     */
    private function agent(User $user, ?AgentStat $latest, ?array $latestStats = null, ?array $history = null, ?array $events = null, ?CustomMedals $customMedals = null): Agent
    {
        return new Agent(
            (string) $user->getAgentName(),
            $latest?->faction,
            $latest?->level,
            $user->getVisibleAvatar(),
            $latestStats,
            $history,
            $events,
            $customMedals,
        );
    }

    /**
     * A stats row as /api/agent-stats has it, without the id (that is the owner's only).
     *
     * @return array<string, int|string|null>
     */
    private function row(AgentStat $stat): array
    {
        return [
            'datetime' => $stat->datetime->format(AgentStatInput::DATETIME_FORMAT),
            'nickname' => $stat->nickname,
            'faction' => $stat->faction,
            ...$stat->getStats(),
        ];
    }
}
