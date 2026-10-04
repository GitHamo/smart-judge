<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Application;

use Potato\SmartJudge\Domain\Context;
use Potato\SmartJudge\Domain\Driver;
use Potato\SmartJudge\Domain\Fingerprint;
use Potato\SmartJudge\Domain\InvalidQuestion;
use Potato\SmartJudge\Domain\JudgeUnavailable;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Rule;
use Potato\SmartJudge\Domain\Subject;
use Potato\SmartJudge\Domain\Verdict;

/**
 * Asks a driver questions about subjects and gives back a probability, or a rule's verdict, per subject, keyed by the consumer's keys.
 */
final readonly class Judge
{
    // models get less accurate as a request grows, so subjects are asked in small batches
    public const int BATCH_SIZE = 20;
    private const string QUESTION_SEPARATOR = '__';

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
     * @throws JudgeUnavailable when the driver gives no usable answer, so the consumer falls back
     */
    public function ask(
        array $subjects,
        string $name,
        Question $question,
        ?Context $context = null,
        int $batchSize = self::BATCH_SIZE,
    ): array {
        return array_map(
            static fn (array $probabilities): float => $probabilities[0],
            $this->probabilities($subjects, $name, [$question], $context, $batchSize),
        );
    }

    /**
     * @param list<Subject> $subjects
     * @param string $name the consumer's name for its kind of subject, e.g. `payee`; ids read `<name>_<key>`
     * @param int $batchSize the most subjects asked about in one request to the driver, at least 1; each with all the rule's questions
     *
     * @return array<int|string, Verdict> verdict per subject key
     *
     * @throws InvalidQuestion when a question has not one placeholder, two subjects share a key or the batch size is not positive
     * @throws JudgeUnavailable when the driver gives no usable answer, so the consumer falls back
     */
    public function decide(
        array $subjects,
        string $name,
        Rule $rule,
        ?Context $context = null,
        int $batchSize = self::BATCH_SIZE,
    ): array {
        $source = $this->driver->name();

        return array_map(
            static fn (array $probabilities): Verdict => $rule->decide($probabilities, $source),
            $this->probabilities($subjects, $name, $rule->questions(), $context, $batchSize),
        );
    }

    /**
     * The fingerprint of what is asked and this judge's driver, to tell when stored verdicts are stale.
     *
     * @param array<int|string, Question|Rule> $asked
     */
    public function fingerprint(array $asked): Fingerprint
    {
        return Fingerprint::of($asked, $this->driver->name());
    }

    /**
     * @param list<Subject> $subjects
     * @param non-empty-array<int|string, Question> $questions asked about each subject, keyed by a name
     *
     * @return array<int|string, array<int|string, float>> probability of "yes" per subject key and question name
     *
     * @throws InvalidQuestion
     * @throws JudgeUnavailable
     */
    private function probabilities(array $subjects, string $name, array $questions, ?Context $context, int $batchSize): array
    {
        if ($batchSize < 1) {
            throw new InvalidQuestion(\sprintf('Batch size must be at least 1, %d given.', $batchSize));
        }

        // per subject key: its id, the ids of its questions by name, and its questions by id
        $ids = [];
        $questionIds = [];
        $filled = [];
        $asked = [];

        // every question is built before the first request, so a mistake never costs a request
        foreach ($subjects as $subject) {
            if (isset($ids[$subject->key])) {
                throw new InvalidQuestion(\sprintf('Subject key "%s" is given more than once.', $subject->key));
            }

            $id = $name . '_' . $subject->key;
            $ids[$subject->key] = $id;
            $questionIds[$subject->key] = [];
            $filled[$subject->key] = [];

            foreach ($questions as $questionName => $question) {
                // a rule with one question, such as a flag, keeps the subject's id, so it asks just as its question alone
                $questionId = 1 === \count($questions) ? $id : $id . self::QUESTION_SEPARATOR . $questionName;

                // e.g. subject `3` with option `x__essential` and subject `3__x` with option `essential`
                if (isset($asked[$questionId])) {
                    throw new InvalidQuestion(\sprintf('Question id "%s" is given more than once; choose other subject keys or question names.', $questionId));
                }

                $asked[$questionId] = true;
                $questionIds[$subject->key][$questionName] = $questionId;
                $filled[$subject->key][$questionId] = $question->about($id);
            }
        }

        $answers = [];

        foreach (array_chunk($subjects, $batchSize) as $batch) {
            $facts = [];
            $batchQuestions = [];

            foreach ($batch as $subject) {
                $facts[$ids[$subject->key]] = $subject->facts;
                $batchQuestions = [...$batchQuestions, ...$filled[$subject->key]];
            }

            $answers = [...$answers, ...$this->driver->answer($facts, $batchQuestions, $context)];
        }

        return array_map(
            static fn (array $questionIdsByName): array => array_map(
                static fn (string $questionId): float => $answers[$questionId],
                $questionIdsByName,
            ),
            $questionIds,
        );
    }
}
