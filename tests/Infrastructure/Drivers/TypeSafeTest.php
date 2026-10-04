<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Infrastructure\Drivers;

use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Infrastructure\Drivers\TypeSafe;

final class TypeSafeTest extends TestCase
{
    public function testIsNamedAfterItsModel(): void
    {
        self::assertSame('typesafe:jev-1.13.0', (new TypeSafe('secret-key', 'jev-1.13.0'))->name());
    }
}
