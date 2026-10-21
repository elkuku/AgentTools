<?php

namespace App\Controller\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class GitHubController extends AbstractController
{
    /**
     * Link to this controller to start the "connect" process.
     */
    #[Route(path: '/connect/github', name: 'connect_github_start', methods: ['GET'])]
    public function connect(
        ClientRegistry $clientRegistry
    ): RedirectResponse {
        return $clientRegistry
            ->getClient('github')
            ->redirect(
                [
                    'user:email', // the scopes you want to access
                ],
                []
            );
    }

    /**
     * After going to GitHub, you're redirected back here
     * because this is the "redirect_route" you configured
     * in config/packages/knpu_oauth2_client.yaml.
     */
    #[Route(path: '/connect/check/github', name: 'connect_github_check', methods: ['GET'])]
    public function connectCheck(): RedirectResponse
    {
        return $this->redirectToRoute('app_default');
    }
}
