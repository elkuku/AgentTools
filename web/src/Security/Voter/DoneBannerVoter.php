<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DoneBanner;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Only the owner sees a done banner alone and sets its day; others see the list with the
 * Bannergress part.
 *
 * @extends Voter<string, DoneBanner>
 */
final class DoneBannerVoter extends Voter
{
    public const string EDIT = 'DONE_BANNER_EDIT';

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof DoneBanner;
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User
            && $subject->owner?->id === $user->id;
    }
}
