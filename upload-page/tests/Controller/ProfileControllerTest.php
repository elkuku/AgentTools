<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ProfileControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $userRepository->findOneBy(['identifier' => 'user']);
        if (null !== $user) {
            $user->setParams([]);
            $em->flush();
        }

        parent::tearDown();
    }

    public function testProfileRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/profile');

        self::assertResponseRedirects();
    }

    public function testProfileRendersForAuthenticatedUser(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/profile');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('User Profile');
        self::assertSelectorExists('form');
    }

    public function testProfileFormSavesParams(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $crawler = $client->request(Request::METHOD_GET, '/profile');

        $csrfToken = $crawler->filter('input[name="user_params[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, '/profile', [
            'user_params' => [
                'user_name' => 'test_username',
                '_token' => $csrfToken,
            ],
        ]);

        self::assertResponseIsSuccessful();

        $user = $userRepository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);
        self::assertSame('test_username', $user->getParam('user_name'));
    }
}
