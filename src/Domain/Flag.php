<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

use Override;

/**
 * A rule with one question: "yes" when its probability reaches the threshold, otherwise "no".
 */
final readonly class Flag implements Rule
{
    private const string QUESTION = 'flag';

    /**
     * @param float $threshold the lowest probability that means "yes", between 0 and 1
     */
    public function __construct(
        private Question $question,
        private float $threshold,
    ) {
    }

    #[Override]
    public function questions(): array
    {
        return [self::QUESTION => $this->question];
    }

    #[Override]
    public function decide(array $probabilities, string $source): Verdict
    {
        $probability = $probabilities[self::QUESTION];

        return new Verdict($probability >= $this->threshold, $probability, $source);
    }
}
