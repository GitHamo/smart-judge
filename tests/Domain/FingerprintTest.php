<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Domain;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Domain\Choice;
use Potato\SmartJudge\Domain\Fingerprint;
use Potato\SmartJudge\Domain\Flag;
use Potato\SmartJudge\Domain\Option;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Rule;
use Potato\SmartJudge\Domain\Verdict;

final class FingerprintTest extends TestCase
{
    public function testIsEqualForEqualRulesAndDriver(): void
    {
        self::assertEquals(
            Fingerprint::of(self::rules(), 'typesafe:jev-1.13.0'),
            Fingerprint::of(self::rules(), 'typesafe:jev-1.13.0'),
        );
    }

    public function testIsEqualForEqualQuestionsAndDriver(): void
    {
        self::assertEquals(
            Fingerprint::of([self::question()], 'typesafe:jev-1.13.0'),
            Fingerprint::of([new Question('Is `%s` a subscription?', 'A subscription.', 'No subscription.')], 'typesafe:jev-1.13.0'),
        );
    }

    public function testTellsAConsumersOwnRulesApartByTheirSettings(): void
    {
        $rule = static fn (float $threshold): Rule => new readonly class($threshold) implements Rule {
            public function __construct(private float $threshold)
            {
            }

            #[Override]
            public function questions(): array
            {
                return ['monthly' => new Question('Is `%s` paid monthly?', 'Monthly.', 'Not monthly.')];
            }

            #[Override]
            public function decide(array $probabilities, string $source): Verdict
            {
                return new Verdict($probabilities['monthly'] >= $this->threshold, $probabilities['monthly'], $source);
            }
        };

        self::assertEquals(Fingerprint::of([$rule(0.6)], 'typesafe:jev-1.13.0'), Fingerprint::of([$rule(0.6)], 'typesafe:jev-1.13.0'));
        self::assertNotEquals(Fingerprint::of([$rule(0.6)], 'typesafe:jev-1.13.0'), Fingerprint::of([$rule(0.7)], 'typesafe:jev-1.13.0'));
    }

    /**
     * @param list<Question|Rule> $asked
     */
    #[DataProvider('changesDataProvider')]
    public function testChangesWithAnyChange(array $asked, string $driver): void
    {
        self::assertNotEquals(Fingerprint::of(self::rules(), 'typesafe:jev-1.13.0'), Fingerprint::of($asked, $driver));
    }

    /**
     * @return array<string, array{list<Question|Rule>, string}>
     */
    public static function changesDataProvider(): array
    {
        [$flag, $choice] = self::rules();
        [$essential, $discretionary] = self::options();

        return [
            'another driver' => [self::rules(), 'typesafe:jev-1.14.0'],
            'another threshold' => [[new Flag(self::question(), 0.7), $choice], 'typesafe:jev-1.13.0'],
            'another question text' => [[new Flag(new Question('Is `%s` monthly?', 'A subscription.', 'No subscription.'), 0.6), $choice], 'typesafe:jev-1.13.0'],
            'other criteria' => [[new Flag(new Question('Is `%s` a subscription?', 'Paid monthly.', 'No subscription.'), 0.6), $choice], 'typesafe:jev-1.13.0'],
            'another order of options' => [[$flag, new Choice([$discretionary, $essential], 0.6)], 'typesafe:jev-1.13.0'],
            'another option value' => [[$flag, new Choice([new Option('needed', $essential->question), $discretionary], 0.6)], 'typesafe:jev-1.13.0'],
            'another order of rules' => [[$choice, $flag], 'typesafe:jev-1.13.0'],
            'one rule less' => [[$flag], 'typesafe:jev-1.13.0'],
            'the question instead of its flag' => [[self::question(), $choice], 'typesafe:jev-1.13.0'],
        ];
    }

    /**
     * @return list<Rule>
     */
    private static function rules(): array
    {
        return [new Flag(self::question(), 0.6), new Choice(self::options(), 0.6)];
    }

    /**
     * @return list<Option>
     */
    private static function options(): array
    {
        return [
            new Option('essential', new Question('Is `%s` essential?', 'Essential.', 'Not essential.')),
            new Option('discretionary', new Question('Is `%s` discretionary?', 'Discretionary.', 'Not discretionary.')),
        ];
    }

    private static function question(): Question
    {
        return new Question('Is `%s` a subscription?', 'A subscription.', 'No subscription.');
    }
}
