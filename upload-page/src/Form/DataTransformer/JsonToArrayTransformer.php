<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Converts between a decoded JSON object (array) and its JSON text.
 *
 * @implements DataTransformerInterface<array<mixed>, string>
 */
final class JsonToArrayTransformer implements DataTransformerInterface
{
    #[\Override]
    public function transform(mixed $value): string
    {
        if (null === $value || [] === $value) {
            return '';
        }

        return (string) \json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function reverseTransform(mixed $value): array
    {
        if (null === $value || '' === \trim((string) $value)) {
            return [];
        }

        try {
            $data = \json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new TransformationFailedException($jsonException->getMessage(), 0, $jsonException);
        }

        if (!\is_array($data)) {
            throw new TransformationFailedException('The JSON value must be an object.');
        }

        return $data;
    }
}
