<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Security\GoogleIdentityAuthenticator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Posts a Google sign-in as the Sign in with Google button does: the ID token and the same
 * g_csrf_token as a cookie and a field.
 */
final class GoogleSignIn
{
    public static function post(KernelBrowser $client, string $idToken): void
    {
        $client->getCookieJar()->set(new Cookie(GoogleIdentityAuthenticator::CSRF_TOKEN, 'test-csrf'));
        $client->request(Request::METHOD_POST, '/connect/google/verify', [
            'credential' => $idToken,
            GoogleIdentityAuthenticator::CSRF_TOKEN => 'test-csrf',
        ]);
    }
}
