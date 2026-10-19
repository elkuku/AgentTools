<?php

declare(strict_types=1);

namespace App\Tests\Controller\Upload;

use App\Entity\Upload;
use App\Entity\User;
use App\Enum\UploadSource;
use App\Repository\UploadRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\Upload')->execute();

        parent::tearDown();
    }

    public function testPagesRequireAuthentication(): void
    {
        foreach (['/uploads', '/uploads/new'] as $path) {
            $this->client->request(Request::METHOD_GET, $path);
            self::assertResponseRedirects();
        }
    }

    public function testIndexListsOnlyOwnUploads(): void
    {
        $this->createUpload($this->getUserByIdentifier('user'), 'Mine');
        $this->createUpload($this->getUserByIdentifier('admin'), 'Not mine');

        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/uploads');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Mine');
        self::assertSelectorTextNotContains('table', 'Not mine');
    }

    public function testShowRendersData(): void
    {
        $upload = $this->createUpload($this->getUserByIdentifier('user'), 'Show me', ['answer' => 42]);

        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/uploads/'.$upload->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#upload-title', 'Show me');
        self::assertSelectorTextContains('#upload-data', '"answer": 42');
    }

    public function testShowDeniesOtherUsersUpload(): void
    {
        $upload = $this->createUpload($this->getUserByIdentifier('admin'), 'Private');

        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/uploads/'.$upload->id);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testFormCreatesUpload(): void
    {
        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/uploads/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Upload', [
            'upload[title]' => 'From the web',
            'upload[data]' => '{"color": "blue", "count": 3}',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#upload-title', 'From the web');

        $uploads = $this->uploadRepository()->findAll();
        self::assertCount(1, $uploads);
        self::assertSame(['color' => 'blue', 'count' => 3], $uploads[0]->getData());
        self::assertSame(UploadSource::WEB, $uploads[0]->getSource());
    }

    public function testFormRejectsInvalidJson(): void
    {
        $this->assertFormError('{"color": ', 'Please enter valid JSON.');
    }

    public function testFormRejectsJsonList(): void
    {
        $this->assertFormError('[1, 2, 3]', 'The data must be a JSON object, not a list.');
    }

    public function testFormRejectsEmptyData(): void
    {
        $this->assertFormError('', 'The data must not be empty.');
    }

    private function assertFormError(string $json, string $message): void
    {
        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/uploads/new');

        $this->client->submitForm('Upload', [
            'upload[title]' => 'Broken',
            'upload[data]' => $json,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('form[name="upload"]', $message);
        self::assertSame([], $this->uploadRepository()->findAll());
    }

    /**
     * @param array<mixed> $data
     */
    private function createUpload(User $owner, string $title, array $data = ['a' => 1]): Upload
    {
        $upload = new Upload($owner, $title, $data, UploadSource::APP);
        $this->entityManager()->persist($upload);
        $this->entityManager()->flush();

        return $upload;
    }

    private function getUserByIdentifier(string $identifier): User
    {
        $repository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        $user = $repository->findOneBy(['identifier' => $identifier]);
        self::assertNotNull($user);

        return $user;
    }

    private function uploadRepository(): UploadRepository
    {
        $repository = static::getContainer()->get(UploadRepository::class);
        self::assertInstanceOf(UploadRepository::class, $repository);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
