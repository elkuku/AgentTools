<?php

namespace App\Controller\User;

use App\Controller\BaseController;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/{id}', name: 'app_user_show', methods: ['GET'])]
#[IsGranted(UserRole::ADMIN->value)]
final class Read extends BaseController
{
    public function __invoke(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }
}
