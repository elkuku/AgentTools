<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\TimelineEvent;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Only the owner sees (as a single event), changes and deletes an event; others see it on the
 * timeline with the stats history.
 *
 * @extends Voter<string, TimelineEvent>
 */
final class TimelineEventVoter extends Voter
{
    public const string EDIT = 'TIMELINE_EVENT_EDIT';

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof TimelineEvent;
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User
            && $subject->owner?->id === $user->id;
    }
}
