<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * One AI model behind the judge, reached through its provider.
 */
interface Driver
{
    /**
     * Says which model answers, e.g. `typesafe:jev-1.13.0`.
     */
    public function name(): string;

    /**
     * Answers one request.
     *
     * @param array<string, array<string, mixed>> $facts the facts of each subject, keyed by the subject's id
     * @param array<string, Question> $questions questions with their placeholder filled, keyed by question id
     * @param Context|null $context facts shared by every subject, sent once under {@see Context::KEY}
     *
     * @return array<string, float> probability of "yes" per question id, between 0 and 1
     *
     * @throws JudgeUnavailable when the driver gives no usable answer
     */
    public function answer(array $facts, array $questions, ?Context $context): array;
}
