<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class UserAccessTest extends WebTestCase
{
    public function testUserCanLogin(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'AgentTools');

        /**
         * @var UserRepository $userRepository
         */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /**
         * @var \Symfony\Component\Security\Core\User\UserInterface $user
         */
        $user = $userRepository->findOneBy(['identifier' => 'user']);

        $client->loginUser($user);

        $client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h4', 'Welcome user');
        // No waiting for an administrator
        self::assertSelectorTextContains('nav', 'Agent stats');
        // The user menu as the app's account menu: the own stats, timeline and banners first
        self::assertSame(
            ['/agent-stats', '/agent-stats/timeline', '/banners', '/profile', '/agent-stats/import', '/logout'],
            $client->getCrawler()->filter('#dropdownMenuButton1 + .dropdown-menu a.dropdown-item')->each(static fn ($link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('#dropdownMenuButton1 + .dropdown-menu', 'My timeline');
    }
}
