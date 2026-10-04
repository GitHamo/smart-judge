<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

/**
 * A yes/no question about one subject, with the criteria of what "yes" and "no" mean.
 */
final readonly class Question
{
    private const string PLACEHOLDER = '%s';

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
     *
     * @throws InvalidQuestion when the text has no placeholder, so the model would not know which subject is meant
     */
    public function about(string $subjectId): self
    {
        if (!str_contains($this->text, self::PLACEHOLDER)) {
            throw new InvalidQuestion(\sprintf('Question "%s" has no %s placeholder for the subject.', $this->text, self::PLACEHOLDER));
        }

        return new self(\sprintf($this->text, $subjectId), $this->yes, $this->no);
    }
}
