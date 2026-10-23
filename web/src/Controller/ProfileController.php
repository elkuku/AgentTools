<?php

namespace App\Controller;

use App\Enum\UserRole;
use App\Security\ApiTokenStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile', name: 'app_profile', methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
class ProfileController extends BaseController
{
    public function __invoke(Request $request, ApiTokenStore $apiTokenStore): Response
    {
        return $this->render('default/profile.html.twig', [
            'apiToken' => $this->isGranted(UserRole::ADMIN->value)
                ? $apiTokenStore->get($request->getSession(), $this->getUser())
                : null,
        ]);
    }
}
