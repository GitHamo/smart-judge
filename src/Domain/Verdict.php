<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * The outcome of a rule for one subject.
 */
final readonly class Verdict
{
    /**
     * @param bool|int|float|string|null $value the decided value, null when the rule decided on none
     * @param float $probability of "yes" to the question the decision rests on, between 0 and 1,
     *                           e.g. 0.2 for a flag that says "no", or of the most likely option of a choice that decided on none
     * @param string $source the name of the driver that answered, e.g. `typesafe:jev-1.13.0`
     */
    public function __construct(
        public bool|int|float|string|null $value,
        public float $probability,
        public string $source,
    ) {
    }
}
