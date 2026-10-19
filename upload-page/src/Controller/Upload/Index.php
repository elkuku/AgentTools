<?php

declare(strict_types=1);

namespace App\Controller\Upload;

use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Repository\UploadRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/uploads', name: 'app_upload_index', methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Index extends BaseController
{
    public function __invoke(UploadRepository $uploadRepository): Response
    {
        return $this->render('upload/index.html.twig', [
            'uploads' => $uploadRepository->findByOwner($this->getUser()),
        ]);
    }
}
