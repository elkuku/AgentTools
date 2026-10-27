<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Bannergress;
use App\ApiResource\CustomMedals;
use App\ApiResource\Me;
use App\Bannergress\DoneBannerSync;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * PATCH /api/me: sets the current user's agent name (422 if another user has it), the groups
 * of custom medals sent, the visible profile parts and the Bannergress sync; null keeps them.
 *
 * @implements ProcessorInterface<Me, Me>
 */
final readonly class MeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private Security $security,
        private DoneBannerSync $doneBannerSync,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Me
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The operation requires ROLE_USER.');
        }

        if (null !== $data->agentName) {
            $agentName = \trim($data->agentName);

            $owner = $this->userRepository->findOneByAgentName($agentName);
            if ($owner instanceof User && $owner !== $user) {
                throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(User::AGENT_NAME_TAKEN, null, [], $data, 'agentName', $agentName)]));
            }

            $user->setAgentName($agentName);
            $data->agentName = $agentName;
        }

        $this->saveParts($data, $user, $this->sentFields($context));

        $this->entityManager->flush();

        // What is stored, also for fields sent as null
        $data->agentName = $user->getAgentName();
        $data->customMedals = CustomMedals::fromArray($user->getCustomMedals());
        $data->visibleParts = $user->getVisibleParts();
        $data->visibilityChosen = $user->getVisibilityChosenAt() instanceof \DateTimeImmutable;
        $data->bannergress = Bannergress::fromStored($user->getBannergress());

        return $data;
    }

    /**
     * The custom medals, the Bannergress sync and the visible parts, those sent. A merge patch
     * starts from the stored user, so the sync and the parts are only saved when [$sent] has them:
     * else a custom medals save would count as choosing the parts and bump the sync time.
     *
     * @param list<string> $sent
     */
    private function saveParts(Me $data, User $user, array $sent): void
    {
        if ($data->customMedals instanceof CustomMedals) {
            $user->setCustomMedals($data->customMedals->toArray());
        }

        if ($data->bannergress instanceof Bannergress && \in_array('bannergress', $sent, true)) {
            $this->saveBannergress($data->bannergress, $user);
        }

        if (null !== $data->visibleParts && \in_array('visibleParts', $sent, true)) {
            $user->chooseVisibleParts($data->visibleParts, new \DateTimeImmutable());
        }
    }

    /** The counts and the to-do banners on the user, the done list as DoneBanner. */
    private function saveBannergress(Bannergress $bannergress, User $user): void
    {
        $bannergress->syncedAt = new \DateTimeImmutable()->format(\DATE_ATOM);
        $user->setBannergress($bannergress->toArray());
        $this->doneBannerSync->replace($user, $bannergress->doneBanners);
    }

    /**
     * The fields of the request body.
     *
     * @param array<string, mixed> $context
     *
     * @return list<string>
     */
    private function sentFields(array $context): array
    {
        $request = $context['request'] ?? null;
        $body = $request instanceof Request ? \json_decode($request->getContent(), true) : null;

        return \is_array($body) ? \array_map(strval(...), \array_keys($body)) : [];
    }
}
