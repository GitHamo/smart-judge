<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

use Override;

/**
 * A rule with one question per option: the most likely option wins, the first listed wins ties,
 * and none wins below the threshold.
 */
final readonly class Choice implements Rule
{
    /** @var non-empty-array<int|string, Question> */
    private array $questions;

    /**
     * @param list<Option> $options in order of preference
     * @param float $threshold the lowest probability an option needs to win, between 0 and 1
     *
     * @throws InvalidQuestion when there is no option or two options share a value, so the choice cannot decide
     */
    public function __construct(
        private array $options,
        private float $threshold,
    ) {
        $questions = [];

        foreach ($options as $option) {
            if (isset($questions[$option->value])) {
                throw new InvalidQuestion(\sprintf('Option "%s" is given more than once.', $option->value));
            }

            $questions[$option->value] = $option->question;
        }

        if ([] === $questions) {
            throw new InvalidQuestion('A choice needs at least one option.');
        }

        $this->questions = $questions;
    }

    #[Override]
    public function questions(): array
    {
        return $this->questions;
    }

    #[Override]
    public function decide(array $probabilities, string $source): Verdict
    {
        $best = null;
        $bestProbability = 0.0;

        foreach ($this->options as $option) {
            $probability = $probabilities[$option->value];

            // strictly greater, so the first of equally likely options wins
            if (null === $best || $probability > $bestProbability) {
                $best = $option->value;
                $bestProbability = $probability;
            }
        }

        return new Verdict($bestProbability >= $this->threshold ? $best : null, $bestProbability, $source);
    }
}
