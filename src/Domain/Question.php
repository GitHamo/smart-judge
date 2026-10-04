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
     * @throws InvalidQuestion when the text has no placeholder or more than one, so the subject meant is unclear
     */
    public function about(string $subjectId): self
    {
        if (1 !== substr_count($this->text, self::PLACEHOLDER)) {
            throw new InvalidQuestion(\sprintf('Question "%s" needs exactly one %s placeholder for the subject.', $this->text, self::PLACEHOLDER));
        }

        // not sprintf: any other % in the text is meant literally, e.g. "more than 50%"
        return new self(str_replace(self::PLACEHOLDER, $subjectId, $this->text), $this->yes, $this->no);
    }
}
