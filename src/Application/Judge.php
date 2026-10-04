<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Application;

use Potato\SmartJudge\Domain\Context;
use Potato\SmartJudge\Domain\Driver;
use Potato\SmartJudge\Domain\InvalidQuestion;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Subject;

/**
 * Asks a driver questions about subjects and gives back a probability per subject, keyed by the consumer's keys.
 */
final readonly class Judge
{
    // models get less accurate as a request grows, so subjects are asked in small batches
    public const int BATCH_SIZE = 20;

    public function __construct(
        private Driver $driver,
    ) {
    }

    /**
     * @param list<Subject> $subjects
     * @param string $name the consumer's name for its kind of subject, e.g. `pair`; ids read `<name>_<key>`
     * @param int $batchSize the most subjects asked about in one request to the driver, at least 1
     *
     * @return array<int|string, float> probability of "yes" per subject key
     *
     * @throws InvalidQuestion when the question has not one placeholder, two subjects share a key or the batch size is not positive
     */
    public function ask(
        array $subjects,
        string $name,
        Question $question,
        ?Context $context = null,
        int $batchSize = self::BATCH_SIZE,
    ): array {
        if ($batchSize < 1) {
            throw new InvalidQuestion(\sprintf('Batch size must be at least 1, %d given.', $batchSize));
        }

        $ids = [];
        $questions = [];

        // every question is built before the first request, so a mistake never costs a request
        foreach ($subjects as $subject) {
            if (isset($ids[$subject->key])) {
                throw new InvalidQuestion(\sprintf('Subject key "%s" is given more than once.', $subject->key));
            }

            $id = $name . '_' . $subject->key;
            $ids[$subject->key] = $id;
            $questions[$id] = $question->about($id);
        }

        $probabilities = [];

        foreach (array_chunk($subjects, $batchSize) as $batch) {
            $facts = [];

            foreach ($batch as $subject) {
                $facts[$ids[$subject->key]] = $subject->facts;
            }

            $probabilities = [
                ...$probabilities,
                ...$this->driver->answer($facts, array_intersect_key($questions, $facts), $context),
            ];
        }

        return array_map(static fn (string $id): float => $probabilities[$id], $ids);
    }
}
