<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
class LoginFormController extends AbstractController
{
    public function __invoke(
        AuthenticationUtils $authenticationUtils,
        #[Autowire('%env(OAUTH_GOOGLE_ID)%')] string $oauthGoogleId,
    ): Response {
        return $this->render(
            'auth/login.html.twig',
            [
                'last_username' => $authenticationUtils->getLastUsername(),
                'error' => $authenticationUtils->getLastAuthenticationError(),
                'oauthGoogleId' => $oauthGoogleId,
            ]
        );
    }
}
