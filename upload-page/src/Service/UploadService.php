<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UploadInput;
use App\Entity\Upload;
use App\Entity\User;
use App\Enum\UploadSource;
use Doctrine\ORM\EntityManagerInterface;

class UploadService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(User $owner, UploadInput $input, UploadSource $source): Upload
    {
        $upload = new Upload($owner, $input->title, $input->data, $source);

        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        return $upload;
    }
}
