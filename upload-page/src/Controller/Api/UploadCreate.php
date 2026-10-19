<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Dto\UploadInput;
use App\Enum\UploadSource;
use App\Enum\UserRole;
use App\Service\UploadService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/uploads', name: 'api_upload_create', methods: ['POST'], format: 'json')]
#[IsGranted(UserRole::USER->value)]
final class UploadCreate extends BaseController
{
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')] UploadInput $input,
        UploadService $uploadService,
    ): JsonResponse {
        $upload = $uploadService->create($this->getUser(), $input, UploadSource::APP);

        return $this->json(
            [
                'id' => $upload->id,
                'createdAt' => $upload->getCreatedAt()->format(\DATE_ATOM),
            ],
            Response::HTTP_CREATED,
            [
                'Location' => $this->generateUrl(
                    'app_upload_show',
                    ['id' => $upload->id],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        );
    }
}
