<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * The identity of a set of questions or rules and the driver that answers them; when it changes, earlier verdicts are stale.
 */
final readonly class Fingerprint
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param array<int|string, Question|Rule> $asked in the order they are asked; any change of a rule, its questions or that order gives another fingerprint
     * @param string $driver the name of the driver, e.g. `typesafe:jev-1.13.0`
     */
    public static function of(array $asked, string $driver): self
    {
        return new self(hash('sha256', json_encode(
            [$driver, self::described($asked)],
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
        )));
    }

    /**
     * Every object with its class and all its properties, so every rule, even a consumer's own, counts with all its settings.
     *
     * Not serialize(): it refuses anonymous classes and closures, both fair ways to write a rule.
     */
    private static function described(mixed $value): mixed
    {
        if (\is_object($value)) {
            // the array cast holds private and protected properties too
            return [$value::class, self::described((array) $value)];
        }

        return \is_array($value) ? array_map(self::described(...), $value) : $value;
    }
}
