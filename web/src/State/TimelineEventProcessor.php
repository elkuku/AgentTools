<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TimelineEvent;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * POST /api/timeline-events: the event belongs to the current user.
 *
 * @implements ProcessorInterface<TimelineEvent, TimelineEvent>
 */
final readonly class TimelineEventProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<TimelineEvent, TimelineEvent> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TimelineEvent
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The operation requires ROLE_USER.');
        }

        $data->owner = $user;

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
