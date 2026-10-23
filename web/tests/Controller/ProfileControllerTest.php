<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
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

        foreach ($userRepository->findBy(['identifier' => ['user', 'admin']]) as $user) {
            $user->setGoogleId(null);
        }

        $em->flush();

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
        self::assertSelectorNotExists('form');
    }

    public function testProfileShowsTheApiTokenOfAGoogleSignIn(): void
    {
        $client = static::createClient();
        $idToken = FakeGoogleIdTokenVerifier::token('gid-profile', 'admin');

        $client->request(Request::METHOD_POST, '/connect/google/verify', ['credential' => $idToken]);
        self::assertResponseRedirects();

        $client->request(Request::METHOD_GET, '/profile');

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('api-token', $idToken);
        self::assertSelectorExists('[data-controller="clipboard"] button[data-action="clipboard#copy"]');
    }

    public function testProfileHidesTheApiTokenFromNonAdmins(): void
    {
        $client = static::createClient();
        $idToken = FakeGoogleIdTokenVerifier::token('gid-profile-user', 'user');

        $client->request(Request::METHOD_POST, '/connect/google/verify', ['credential' => $idToken]);
        self::assertResponseRedirects();

        $client->request(Request::METHOD_GET, '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#api-token');
        self::assertSelectorTextNotContains('body', 'API token');
    }

    public function testProfileExplainsHowToGetAnApiToken(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#api-token');
        self::assertSelectorTextContains('body', 'sign in with Google to get a token');
    }
}
