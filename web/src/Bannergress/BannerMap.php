<?php

declare(strict_types=1);

namespace App\Bannergress;

use App\ApiResource\Bannergress;
use App\Entity\DoneBanner;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The dots of the banners page's world map (banner_map_controller.js): where each banner with a
 * start point starts, with its list (published, done, todo), title, link and a label.
 */
final readonly class BannerMap
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param list<DoneBanner> $doneBanners
     *
     * @return list<array{lat: float, lng: float, list: string, title: string, url: string, label: string}>
     */
    public function points(Bannergress $bannergress, array $doneBanners): array
    {
        $points = [];
        foreach (['published' => $bannergress->publishedBanners, 'todo' => $bannergress->todoBanners] as $list => $banners) {
            foreach ($banners as $banner) {
                $points[] = $this->point($list, $banner['startLatitude'] ?? null, $banner['startLongitude'] ?? null, $banner['title'], Bannergress::BANNER_BASE.\rawurlencode($banner['id']), $banner['missions']);
            }
        }

        foreach ($doneBanners as $banner) {
            $points[] = $this->point('done', $banner->startLatitude, $banner->startLongitude, $banner->title, $banner->getUrl(), $banner->missions);
        }

        return \array_values(\array_filter($points));
    }

    /**
     * @return array{lat: float, lng: float, list: string, title: string, url: string, label: string}|null null without a start point
     */
    private function point(string $list, float|int|null $latitude, float|int|null $longitude, string $title, string $url, int $missions): ?array
    {
        if (null === $latitude || null === $longitude) {
            return null;
        }

        return [
            'lat' => (float) $latitude,
            'lng' => (float) $longitude,
            'list' => $list,
            'title' => $title,
            'url' => $url,
            'label' => $this->translator->trans('bannergress.'.$list).' · '.$this->translator->trans('bannergress.missions', ['%count%' => $missions]),
        ];
    }
}
