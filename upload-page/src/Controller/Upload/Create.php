<?php

declare(strict_types=1);

namespace App\Controller\Upload;

use App\Controller\BaseController;
use App\Dto\UploadInput;
use App\Enum\UploadSource;
use App\Enum\UserRole;
use App\Form\UploadType;
use App\Service\UploadService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/uploads/new', name: 'app_upload_new', methods: ['GET', 'POST'])]
#[IsGranted(UserRole::USER->value)]
final class Create extends BaseController
{
    public function __invoke(Request $request, UploadService $uploadService): Response
    {
        $form = $this->createForm(UploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadInput $input */
            $input = $form->getData();
            $upload = $uploadService->create($this->getUser(), $input, UploadSource::WEB);

            $this->addFlash('success', 'Upload has been created.');

            return $this->redirectToRoute('app_upload_show', ['id' => $upload->id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('upload/new.html.twig', [
            'form' => $form,
        ]);
    }
}
