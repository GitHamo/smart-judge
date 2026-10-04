<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Domain;

use LogicException;

/**
 * A mistake of the consumer in what it asks; a bug to fix, never a reason to fall back.
 */
final class InvalidQuestion extends LogicException
{
}
