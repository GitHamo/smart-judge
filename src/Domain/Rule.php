<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * How the probabilities of a subject's questions become a verdict.
 *
 * SmartJudge ships {@see Flag} and {@see Choice}; a consumer adds its own rule by implementing this interface.
 */
interface Rule
{
    /**
     * The questions asked about each subject.
     *
     * @return non-empty-array<int|string, Question> keyed by a name unique within the rule
     */
    public function questions(): array;

    /**
     * Decides about one subject.
     *
     * @param array<int|string, float> $probabilities probability of "yes" per question, keyed as {@see self::questions()}
     * @param string $source the name of the driver that answered
     */
    public function decide(array $probabilities, string $source): Verdict;
}
