<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Ingress all-time agent stats as sent by the app. The keys are the agent_stat column names
 * (as in 4e-website); stats Ingress shows but 4e-website does not record are not part of it.
 */
final readonly class AgentStatInput
{
    public const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    /** agent_stat stores the stats as INT. */
    private const int MAX_VALUE = 2147483647;

    public function __construct(
        #[Assert\NotBlank, Assert\DateTime(format: self::DATETIME_FORMAT)]
        public string $datetime = '',
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150)]
        public string $nickname = '',
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 50)]
        public string $faction = '',
        #[Assert\NotNull]
        public ?int $ap = null,
        public ?int $level = null,
        public ?int $explorer = null,
        public ?int $recon = null,
        public ?int $seer = null,
        public ?int $trekker = null,
        public ?int $builder = null,
        public ?int $connector = null,
        #[SerializedName('mind_controller')]
        public ?int $mindController = null,
        public ?int $illuminator = null,
        public ?int $recharger = null,
        public ?int $liberator = null,
        public ?int $pioneer = null,
        public ?int $engineer = null,
        public ?int $purifier = null,
        public ?int $specops = null,
        public ?int $hacker = null,
        public ?int $translator = null,
        public ?int $sojourner = null,
        public ?int $recruiter = null,
        public ?int $missionday = null,
        #[SerializedName('nl1331_meetups')]
        public ?int $nl1331Meetups = null,
        public ?int $ifs = null,
        #[SerializedName('current_challenge')]
        public ?int $currentChallenge = null,
        public ?int $scout = null,
        #[SerializedName('scout_controller')]
        public ?int $scoutController = null,
        #[SerializedName('longest_link')]
        public ?int $longestLink = null,
        #[SerializedName('largest_field')]
        public ?int $largestField = null,
        public ?int $recursions = null,
        #[SerializedName('drone_flight_distance')]
        public ?int $droneFlightDistance = null,
        public ?int $maverick = null,
        #[SerializedName('drone_portals_visited')]
        public ?int $dronePortalsVisited = null,
        #[SerializedName('drone_forced_recalls')]
        public ?int $droneForcedRecalls = null,
        #[SerializedName('kinetic_capsules_completed')]
        public ?int $kineticCapsulesCompleted = null,
        #[SerializedName('months_subscribed')]
        public ?int $monthsSubscribed = null,
        public ?int $epoch = null,
        #[SerializedName('portals_discovered')]
        public ?int $portalsDiscovered = null,
        #[SerializedName('drones_returned')]
        public ?int $dronesReturned = null,
        #[SerializedName('second_sunday')]
        public ?int $secondSunday = null,
        public ?int $reclaimer = null,
    ) {
    }

    public function getDateTime(): \DateTimeImmutable
    {
        $dateTime = \DateTimeImmutable::createFromFormat('!'.self::DATETIME_FORMAT, $this->datetime);
        if (false === $dateTime) {
            throw new \UnexpectedValueException('Invalid datetime, validate the input first.');
        }

        return $dateTime;
    }

    #[Assert\Callback]
    public function validateRanges(ExecutionContextInterface $context): void
    {
        foreach (\get_object_vars($this) as $name => $value) {
            if (\is_int($value) && ($value < 0 || $value > self::MAX_VALUE)) {
                $context->buildViolation('The value must be between 0 and {{ max }}.')
                    ->setParameter('{{ max }}', (string) self::MAX_VALUE)
                    ->atPath($name)
                    ->addViolation();
            }
        }
    }
}
