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
        // serialized, so every rule, even a consumer's own, counts with its class and all its settings
        return new self(hash('sha256', serialize([$driver, $asked])));
    }
}
