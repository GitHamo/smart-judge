<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Domain\Choice;
use Potato\SmartJudge\Domain\InvalidQuestion;
use Potato\SmartJudge\Domain\Option;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Verdict;

final class ChoiceTest extends TestCase
{
    public function testAsksOneQuestionPerOption(): void
    {
        $essential = new Question('Is `%s` an essential expense?', 'Essential.', 'Not essential.');
        $discretionary = new Question('Is `%s` a discretionary expense?', 'Discretionary.', 'Not discretionary.');

        $choice = new Choice([new Option('essential', $essential), new Option('discretionary', $discretionary)], 0.6);

        self::assertSame([$essential, $discretionary], array_values($choice->questions()));
    }

    /**
     * @param array{essential: float, discretionary: float} $probabilities
     */
    #[DataProvider('choicesDataProvider')]
    public function testTakesTheMostLikelyOptionAboveTheThreshold(array $probabilities, ?string $value, float $probability): void
    {
        $choice = new Choice(
            [
                new Option('essential', new Question('Is `%s` essential?', 'Essential.', 'Not essential.')),
                new Option('discretionary', new Question('Is `%s` discretionary?', 'Discretionary.', 'Not discretionary.')),
            ],
            0.6,
        );

        self::assertEquals(
            new Verdict($value, $probability, 'typesafe:jev-1.13.0'),
            $choice->decide(array_combine(array_keys($choice->questions()), $probabilities), 'typesafe:jev-1.13.0'),
        );
    }

    /**
     * @return array<string, array{array{essential: float, discretionary: float}, string|null, float}>
     */
    public static function choicesDataProvider(): array
    {
        return [
            'most likely' => [['essential' => 0.2, 'discretionary' => 0.9], 'discretionary', 0.9],
            'at the threshold' => [['essential' => 0.6, 'discretionary' => 0.1], 'essential', 0.6],
            'below the threshold' => [['essential' => 0.59, 'discretionary' => 0.3], null, 0.59],
            'equally likely: the first option' => [['essential' => 0.8, 'discretionary' => 0.8], 'essential', 0.8],
            'all impossible' => [['essential' => 0.0, 'discretionary' => 0.0], null, 0.0],
        ];
    }

    /**
     * @param list<Option> $options
     */
    #[DataProvider('invalidChoicesDataProvider')]
    public function testRejectsAChoiceThatCannotDecide(array $options): void
    {
        $this->expectException(InvalidQuestion::class);

        new Choice($options, 0.6);
    }

    /**
     * @return array<string, array{list<Option>}>
     */
    public static function invalidChoicesDataProvider(): array
    {
        $question = new Question('Is `%s` essential?', 'Essential.', 'Not essential.');

        return [
            'no options' => [[]],
            // two options of one value could never be told apart
            'duplicate values' => [[new Option('essential', $question), new Option('essential', $question)]],
        ];
    }
}
