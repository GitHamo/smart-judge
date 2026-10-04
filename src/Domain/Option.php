<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * One possible outcome of a choice, with the question that asks whether it applies.
 */
final readonly class Option
{
    /**
     * @param string $value the decided value of a verdict when this option wins, e.g. `essential`
     */
    public function __construct(
        public string $value,
        public Question $question,
    ) {
    }
}
