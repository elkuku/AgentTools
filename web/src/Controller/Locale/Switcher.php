<?php

declare(strict_types=1);

namespace App\Controller\Locale;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The language switcher: remembers the language in a cookie (App\EventListener\LocaleListener)
 * and goes back to the page it was chosen on.
 */
#[Route('/locale/{locale}', name: 'app_locale', requirements: ['locale' => 'en|de|es'], methods: ['GET'])]
class Switcher extends BaseController
{
    public const string COOKIE = 'locale';

    public function __invoke(Request $request, string $locale): Response
    {
        // Only back to a page of this site
        $referer = $request->headers->get('referer') ?? '';
        $back = \str_starts_with($referer, $request->getSchemeAndHttpHost().'/')
            ? $referer
            : $this->generateUrl('app_default');

        $response = $this->redirect($back);
        $response->headers->setCookie(Cookie::create(self::COOKIE, $locale, new \DateTimeImmutable('+1 year'))
            ->withSecure($request->isSecure()));

        return $response;
    }
}
