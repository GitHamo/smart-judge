<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Domain\Flag;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Verdict;

final class FlagTest extends TestCase
{
    public function testAsksItsOneQuestion(): void
    {
        $question = new Question('Is `%s` a subscription?', 'A subscription.', 'No subscription.');

        self::assertSame([$question], array_values((new Flag($question, 0.6))->questions()));
    }

    #[DataProvider('flagsDataProvider')]
    public function testIsYesWhenTheProbabilityReachesTheThreshold(float $probability, bool $value): void
    {
        $flag = new Flag(new Question('Is `%s` a subscription?', 'A subscription.', 'No subscription.'), 0.6);
        $probabilities = array_map(static fn (): float => $probability, $flag->questions());

        self::assertEquals(new Verdict($value, $probability, 'typesafe:jev-1.13.0'), $flag->decide($probabilities, 'typesafe:jev-1.13.0'));
    }

    /**
     * @return array<string, array{float, bool}>
     */
    public static function flagsDataProvider(): array
    {
        return [
            'likely' => [0.95, true],
            'at the threshold' => [0.6, true],
            'unlikely' => [0.2, false],
        ];
    }
}
