<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * A yes/no question about one subject, with the criteria of what "yes" and "no" mean.
 */
final readonly class Question
{
    /**
     * @param string $text the consumer's wording, with a `%s` placeholder for the id of the subject
     */
    public function __construct(
        public string $text,
        public string $yes,
        public string $no,
    ) {
    }

    /**
     * The question about one subject, its placeholder filled with the subject's id.
     */
    public function about(string $subjectId): self
    {
        return new self(\sprintf($this->text, $subjectId), $this->yes, $this->no);
    }
}
