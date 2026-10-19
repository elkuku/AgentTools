<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * An upload as sent by the app (JSON) or the web form.
 */
final readonly class UploadInput
{
    public const int MAX_DATA_BYTES = 65536;

    /**
     * @param array<mixed> $data
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 255)]
        public string $title = '',
        #[Assert\Count(min: 1, minMessage: 'The data must not be empty.')]
        public array $data = [],
    ) {
    }

    #[Assert\Callback]
    public function validateData(ExecutionContextInterface $context): void
    {
        if ([] === $this->data) {
            return;
        }

        if (\array_is_list($this->data)) {
            $context->buildViolation('The data must be a JSON object, not a list.')
                ->atPath('data')
                ->addViolation();

            return;
        }

        if (\strlen((string) \json_encode($this->data)) > self::MAX_DATA_BYTES) {
            $context->buildViolation('The data must not be larger than {{ limit }} bytes.')
                ->setParameter('{{ limit }}', (string) self::MAX_DATA_BYTES)
                ->atPath('data')
                ->addViolation();
        }
    }
}
