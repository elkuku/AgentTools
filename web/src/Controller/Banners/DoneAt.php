<?php

declare(strict_types=1);

namespace App\Controller\Banners;

use App\Controller\BaseController;
use App\Entity\DoneBanner;
use App\Entity\TimelineEvent;
use App\Security\Voter\DoneBannerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sets the day one of the user's banners was done (Y-m-d), or removes it (empty): banners with
 * a day are on the timeline.
 */
#[Route('/banners/done/{id}', name: 'app_banners_done_at', requirements: ['id' => '\d+'], methods: ['POST'])]
#[IsGranted(DoneBannerVoter::EDIT, subject: 'banner')]
final class DoneAt extends BaseController
{
    public const string CSRF_TOKEN = 'done-banner';

    public function __invoke(Request $request, DoneBanner $banner, EntityManagerInterface $entityManager): Response
    {
        $value = $request->getPayload()->getString('doneAt');
        $doneAt = $this->day($value);

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN, $request->getPayload()->getString('_token')) || false === $doneAt) {
            $this->addFlash('danger', 'bannergress.date_invalid');
        } else {
            $banner->doneAt = $doneAt;
            $entityManager->flush();
            $this->addFlash('success', $doneAt instanceof \DateTimeImmutable ? 'bannergress.date_saved' : 'bannergress.date_removed');
        }

        return $this->redirect($this->generateUrl('app_banners').'#done-banner-'.$banner->id, Response::HTTP_SEE_OTHER);
    }

    /** The day of a Y-m-d value; null for none (empty), false for no date. */
    private function day(string $value): \DateTimeImmutable|false|null
    {
        if ('' === $value) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!'.TimelineEvent::DATE_FORMAT, $value);

        return $day instanceof \DateTimeImmutable && $day->format(TimelineEvent::DATE_FORMAT) === $value ? $day : false;
    }
}
