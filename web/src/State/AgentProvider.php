<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Agent;
use App\ApiResource\Bannergress;
use App\ApiResource\CustomMedals;
use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\DoneBanner;
use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
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
        private DoneBannerRepository $doneBannerRepository,
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

        return $this->item($user);
    }

    /** An agent with the parts they show. */
    private function item(User $user): Agent
    {
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
            $user->isVisible(ProfilePart::BANNERGRESS) ? Bannergress::fromStored($user->getBannergress()) : null,
            $user->isVisible(ProfilePart::BANNERGRESS) ? $this->doneBanners($user) : null,
        );
    }

    /**
     * @return list<array{bannerId: string, title: string, missions: int, picture: string|null, startLatitude: float|null, startLongitude: float|null, doneAt: string|null}>
     */
    private function doneBanners(User $user): array
    {
        return \array_map(
            static fn (DoneBanner $banner): array => [
                'bannerId' => $banner->bannerId,
                'title' => $banner->title,
                'missions' => $banner->missions,
                'picture' => $banner->picture,
                'startLatitude' => $banner->startLatitude,
                'startLongitude' => $banner->startLongitude,
                'doneAt' => $banner->doneAt?->format(TimelineEvent::DATE_FORMAT),
            ],
            $this->doneBannerRepository->findByOwner($user),
        );
    }

    /**
     * @param array<string, int|string|null>|null                                                                                                                                $latestStats
     * @param list<array<string, int|string|null>>|null                                                                                                                          $history
     * @param list<array{date: string, endDate: string|null, text: string}>|null                                                                                                 $events
     * @param list<array{bannerId: string, title: string, missions: int, picture: string|null, startLatitude: float|null, startLongitude: float|null, doneAt: string|null}>|null $doneBanners
     */
    private function agent(User $user, ?AgentStat $latest, ?array $latestStats = null, ?array $history = null, ?array $events = null, ?CustomMedals $customMedals = null, ?Bannergress $bannergress = null, ?array $doneBanners = null): Agent
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
            $bannergress,
            $doneBanners,
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
