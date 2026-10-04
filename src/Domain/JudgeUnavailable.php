<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

use RuntimeException;
use Throwable;

/**
 * The driver gave no usable answer, so the consumer falls back to its own way of deciding.
 */
final class JudgeUnavailable extends RuntimeException
{
    /**
     * @param string $driver the name of the driver that failed, e.g. `typesafe:jev-1.13.0`
     * @param int|null $status the HTTP status of the last response, null when there was none
     */
    public function __construct(
        public readonly string $driver,
        public readonly ?int $status,
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('Driver "%s" is unavailable. %s', $driver, $reason), previous: $previous);
    }
}
