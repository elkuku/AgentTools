<?php

declare(strict_types=1);

namespace App\Controller\Upload;

use App\Controller\BaseController;
use App\Entity\Upload;
use App\Security\Voter\UploadVoter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/uploads/{id}', name: 'app_upload_show', requirements: ['id' => '\d+'], methods: ['GET'])]
#[IsGranted(UploadVoter::VIEW, subject: 'upload')]
final class Read extends BaseController
{
    public function __invoke(Upload $upload): Response
    {
        return $this->render('upload/show.html.twig', [
            'upload' => $upload,
        ]);
    }
}
