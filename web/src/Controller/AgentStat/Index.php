<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Enum\UserRole;
use App\Medal\MedalChecker;
use App\Repository\AgentStatRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The user's latest stats with medals and custom medals, as in the app, then every import.
 */
#[Route('/agent-stats', name: 'app_agent_stat_index', methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Index extends BaseController
{
    public function __invoke(AgentStatRepository $agentStatRepository, MedalChecker $medalChecker): Response
    {
        $agentStats = $agentStatRepository->findByOwner($this->getUser());
        $latest = $agentStats[0] ?? null;

        return $this->render('agent_stat/index.html.twig', [
            'agent_stats' => $agentStats,
            'latest' => $latest,
            'first' => \array_last($agentStats),
            'medals' => $latest instanceof AgentStat ? $medalChecker->medalLevels($latest) : [],
            'coreBadges' => $latest instanceof AgentStat ? $medalChecker->coreBadges($latest->getStats()['months_subscribed'] ?? 0) : [],
            'customMedals' => $medalChecker->customMedalTiers($this->getUser()->getCustomMedals()),
        ]);
    }
}
