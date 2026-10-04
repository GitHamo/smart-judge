<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * Facts that hold for every subject of a request, sent once instead of being copied into each subject.
 *
 * Question text refers to them by the word `context`.
 */
final readonly class Context
{
    public const string KEY = 'context';

    /**
     * @param array<string, mixed> $facts named values the model reads, JSON-serializable and possibly nested
     */
    public function __construct(
        public array $facts,
    ) {
    }
}
