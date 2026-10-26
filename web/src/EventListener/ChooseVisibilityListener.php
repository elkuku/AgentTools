<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * A web sign-in of a user who has not chosen the visibility of the profile parts yet leads to
 * /profile. Not for the API (no response to replace) or a remember-me login (neither).
 */
#[AsEventListener]
final readonly class ChooseVisibilityListener
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ('main' !== $event->getFirewallName()
            || !$event->getResponse() instanceof RedirectResponse
            || !$user instanceof User
            || $user->getVisibilityChosenAt() instanceof \DateTimeImmutable
        ) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_profile')));
    }
}
