<?php

namespace App\Tests\Controller\System;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class AboutControllerTest extends WebTestCase
{
    public function testNonAdminIsAccessDenied(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/system/about');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccess(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/system/about');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('pre');
    }
}
