<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Application;

use Potato\SmartJudge\Domain\Driver;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Subject;

/**
 * Asks a driver questions about subjects and gives back a probability per subject, keyed by the consumer's keys.
 */
final readonly class Judge
{
    public function __construct(
        private Driver $driver,
    ) {
    }

    /**
     * @param list<Subject> $subjects
     * @param string $name the consumer's name for its kind of subject, e.g. `pair`; ids read `<name>_<key>`
     *
     * @return array<int|string, float> probability of "yes" per subject key
     */
    public function ask(array $subjects, string $name, Question $question): array
    {
        $ids = [];
        $facts = [];
        $questions = [];

        foreach ($subjects as $subject) {
            $id = $name . '_' . $subject->key;
            $ids[$subject->key] = $id;
            $facts[$id] = $subject->facts;
            $questions[$id] = $question->about($id);
        }

        $probabilities = $this->driver->answer($facts, $questions);

        return array_map(static fn (string $id): float => $probabilities[$id], $ids);
    }
}
