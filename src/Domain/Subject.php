<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * One thing the consumer wants judged: its own key and the facts that describe it.
 *
 * A pair of things compared with each other is one subject whose facts hold both, e.g. `first` and `second`.
 */
final readonly class Subject
{
    /**
     * @param array<string, mixed> $facts named values the model reads, JSON-serializable and possibly nested
     */
    public function __construct(
        public int|string $key,
        public array $facts,
    ) {
    }
}
