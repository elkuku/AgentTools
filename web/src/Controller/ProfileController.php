<?php

namespace App\Controller;

use App\Enum\ProfilePart;
use App\Enum\UserRole;
use App\Form\ProfileVisibilityType;
use App\Security\ApiTokenStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
#[IsGranted(UserRole::USER->value)]
class ProfileController extends BaseController
{
    public function __invoke(Request $request, ApiTokenStore $apiTokenStore, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileVisibilityType::class, ['visibleParts' => $user->getVisibleParts()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<ProfilePart> $parts */
            $parts = $form->get('visibleParts')->getData();
            $user->chooseVisibleParts($parts, new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'profile.visibility.saved');

            return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
        }

        $partHelp = [];
        foreach (ProfilePart::cases() as $part) {
            $partHelp[$part->value] = $part->help();
        }

        return $this->render('default/profile.html.twig', [
            'visibilityForm' => $form,
            'partHelp' => $partHelp,
            'agentPageVisible' => $user->isVisible(ProfilePart::AGENT),
            'apiToken' => $this->isGranted(UserRole::ADMIN->value)
                ? $apiTokenStore->get($request->getSession(), $user)
                : null,
        ]);
    }
}
