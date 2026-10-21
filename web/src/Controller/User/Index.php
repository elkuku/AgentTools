<?php

namespace App\Controller\User;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user', name: 'app_user_index', methods: ['GET'])]
#[IsGranted(UserRole::ADMIN->value)]
final class Index extends BaseController
{
    public function __invoke(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }
}
