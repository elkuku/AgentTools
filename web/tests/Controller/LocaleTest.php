<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Locale\Switcher;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class LocaleTest extends WebTestCase
{
    public function testEnglishByDefault(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('.landing-hero', 'Download the app');
    }

    public function testTheBrowserLanguage(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'es-EC,es;q=0.9,en;q=0.8']);

        self::assertSelectorExists('html[lang="es"]');
        self::assertSelectorTextContains('.landing-hero', 'Descargar la app');
    }

    public function testAnUnknownBrowserLanguageIsEnglish(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);

        self::assertSelectorExists('html[lang="en"]');
    }

    public function testTheSwitcherRemembersTheLanguageAndGoesBack(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/locale/de', server: [
            'HTTP_REFERER' => 'http://localhost/login',
            'HTTP_ACCEPT_LANGUAGE' => 'es',
        ]);

        self::assertResponseRedirects('http://localhost/login');
        self::assertResponseHasCookie(Switcher::COOKIE);

        // The chosen language wins over the browser's
        $client->followRedirect();
        self::assertSelectorExists('html[lang="de"]');
        self::assertSelectorTextContains('.login-card h1', 'Anmelden');
        self::assertSelectorTextContains('#language-selector', 'de');
    }

    public function testTheSwitcherOnlyGoesBackToThisSite(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/locale/es', server: ['HTTP_REFERER' => 'https://example.com/']);

        self::assertResponseRedirects('/');
    }

    public function testOnlyTheEnabledLanguages(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/locale/fr');

        self::assertResponseStatusCodeSame(404);
    }
}
