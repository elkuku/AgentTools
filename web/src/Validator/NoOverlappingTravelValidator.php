<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Repository\TimelineEventRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Looks for another travel of the owner on the event's days; a new event has no owner yet (the
 * form's controller and TimelineEventProcessor set it after validating): it is the current user's.
 */
final class NoOverlappingTravelValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TimelineEventRepository $timelineEventRepository,
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoOverlappingTravel) {
            throw new UnexpectedTypeException($constraint, NoOverlappingTravel::class);
        }

        if (!$value instanceof TimelineEvent || !$value->date instanceof \DateTimeImmutable || !$value->endDate instanceof \DateTimeImmutable) {
            return;
        }

        $owner = $value->owner ?? $this->security->getUser();
        if (!$owner instanceof User) {
            return;
        }

        $other = $this->timelineEventRepository->findOverlappingTravel($owner, $value, $value->date, $value->endDate);
        if (!$other instanceof TimelineEvent) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ travel }}', \sprintf(
                '%s (%s – %s)',
                \strtok($other->text, "\n") ?: '',
                $other->date?->format('Y-m-d'),
                $other->endDate?->format('Y-m-d'),
            ))
            ->atPath('endDate')
            ->addViolation();
    }
}
