<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AgentStat;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Agents (and admins) see their own stats only.
 *
 * @extends Voter<string, AgentStat>
 */
final class AgentStatVoter extends Voter
{
    public const string VIEW = 'AGENT_STAT_VIEW';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof AgentStat;
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User
            && $subject->owner->id === $user->id
            && $this->accessDecisionManager->decide($token, [UserRole::AGENT->value]);
    }
}
