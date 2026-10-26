<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Enum\UserRole;
use App\Medal\MedalChecker;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * An agent's page with the profile parts the agent shows; 404 when the agent part is private.
 * The agent sees it as the others do.
 */
#[Route('/agents/{agentName}', name: 'app_agent_show', requirements: ['agentName' => '[A-Za-z0-9_]{1,50}'], methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Read extends BaseController
{
    public function __invoke(string $agentName, UserRepository $userRepository, AgentStatRepository $agentStatRepository, MedalChecker $medalChecker): Response
    {
        $agent = $userRepository->findOneByAgentName($agentName);
        if (!$agent instanceof User || !$agent->isVisible(ProfilePart::AGENT)) {
            throw $this->createNotFoundException('No agent '.$agentName.' to show.');
        }

        $stats = $agentStatRepository->findByOwner($agent);
        $latest = $stats[0] ?? null;
        $showHistory = $agent->isVisible(ProfilePart::STATS_HISTORY);
        $showLatestStats = $latest instanceof AgentStat && $agent->isVisible(ProfilePart::LATEST_STATS);

        return $this->render('agent/show.html.twig', [
            'agent' => $agent,
            'latest' => $latest,
            // The first import tells about the history
            'first' => $showHistory ? \array_last($stats) : null,
            'history' => $showHistory ? $stats : null,
            'showLatestStats' => $showLatestStats,
            'medals' => $showLatestStats ? $medalChecker->medalLevels($latest) : [],
            'coreBadges' => $showLatestStats ? $medalChecker->coreBadges($latest->getStats()['months_subscribed'] ?? 0) : [],
            'customMedals' => $agent->isVisible(ProfilePart::CUSTOM_MEDALS) ? $medalChecker->customMedalTiers($agent->getCustomMedals()) : null,
            'isOwn' => $agent->id === $this->getUser()->id,
        ]);
    }
}
