<?php

declare(strict_types=1);

namespace App\IngressEvents;

use App\ApiResource\IngressEventList;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The events the calendar shows: the list the Pages workflow publishes every day, so new events
 * need no commit or deploy. It is kept for an hour. When GitHub Pages cannot be reached the last
 * list loaded stays, else data/ingress-events.json, tried again after a few minutes.
 */
final readonly class IngressEventSource
{
    private const string KEY = 'ingress_events';

    private const string LAST_KEY = 'ingress_events_last';

    private const int LIFETIME = 3600;

    private const int RETRY_AFTER = 300;

    private const float TIMEOUT = 3.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private IngressEventFile $file,
        private LoggerInterface $logger,
        #[Autowire(env: 'INGRESS_EVENTS_URL')]
        private string $url,
    ) {
    }

    public function load(): IngressEventList
    {
        if ('' === $this->url) {
            return $this->file->load();
        }

        return $this->cache->get(self::KEY, function (ItemInterface $item): IngressEventList {
            $list = $this->fetch();

            if ($list instanceof IngressEventList) {
                $item->expiresAfter(self::LIFETIME);
                $this->cache->delete(self::LAST_KEY);
                $this->cache->get(self::LAST_KEY, static fn (): IngressEventList => $list);

                return $list;
            }

            $item->expiresAfter(self::RETRY_AFTER);

            return $this->cache->get(self::LAST_KEY, function (ItemInterface $last): IngressEventList {
                // Only a stand-in: not kept as the last list loaded
                $last->expiresAfter(self::RETRY_AFTER);

                return $this->file->load();
            });
        });
    }

    /** The published list, null if there is none (yet) or it cannot be read. */
    private function fetch(): ?IngressEventList
    {
        try {
            $response = $this->httpClient->request('GET', $this->url, ['timeout' => self::TIMEOUT, 'max_duration' => self::TIMEOUT]);
            $list = $this->file->parse($response->getContent());
        } catch (ExceptionInterface $exception) {
            $this->logger->notice('The Ingress events could not be loaded: {message}', ['message' => $exception->getMessage()]);

            return null;
        }

        return !$list instanceof IngressEventList || [] === $list->events ? null : $list;
    }
}
