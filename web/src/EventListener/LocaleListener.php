<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Controller\Locale\Switcher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The language of a request: the one chosen with the language switcher (a cookie), else the
 * browser's preferred one of the enabled locales. Before Symfony's LocaleListener (16), which
 * only overrides it with a `_locale` route attribute (there is none).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class LocaleListener
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private array $enabledLocales,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $chosen = $request->cookies->getString(Switcher::COOKIE);
        $request->setLocale(\in_array($chosen, $this->enabledLocales, true)
            ? $chosen
            : $request->getPreferredLanguage($this->enabledLocales) ?? $request->getDefaultLocale());
    }
}
