<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Application;

use Override;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Application\Judge;
use Potato\SmartJudge\Domain\Choice;
use Potato\SmartJudge\Domain\Context;
use Potato\SmartJudge\Domain\Driver;
use Potato\SmartJudge\Domain\Fingerprint;
use Potato\SmartJudge\Domain\Flag;
use Potato\SmartJudge\Domain\Option;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Rule;
use Potato\SmartJudge\Domain\Subject;
use Potato\SmartJudge\Domain\Verdict;

final class JudgeRulesTest extends TestCase
{
    private Driver&MockObject $driver;

    private Judge $judge;

    #[Override]
    protected function setUp(): void
    {
        $this->driver = $this->createMock(Driver::class);
        $this->driver->method('name')->willReturn('typesafe:jev-1.13.0');
        $this->judge = new Judge($this->driver);
    }

    public function testDecidesAChoicePerSubjectFromOneQuestionPerOption(): void
    {
        $context = new Context(['currency' => 'euro']);

        $this->driver
            ->expects(self::once())
            ->method('answer')
            ->with(
                ['payee_3' => ['name' => 'rewe'], 'payee_7' => ['name' => 'cinema']],
                [
                    'payee_3__essential' => new Question('Is `payee_3` essential?', 'Essential.', 'Not essential.'),
                    'payee_3__discretionary' => new Question('Is `payee_3` discretionary?', 'Discretionary.', 'Not discretionary.'),
                    'payee_7__essential' => new Question('Is `payee_7` essential?', 'Essential.', 'Not essential.'),
                    'payee_7__discretionary' => new Question('Is `payee_7` discretionary?', 'Discretionary.', 'Not discretionary.'),
                ],
                $context,
            )
            ->willReturn([
                'payee_3__essential' => 0.9,
                'payee_3__discretionary' => 0.2,
                'payee_7__essential' => 0.3,
                'payee_7__discretionary' => 0.4,
            ]);

        self::assertEquals(
            [
                3 => new Verdict('essential', 0.9, 'typesafe:jev-1.13.0'),
                7 => new Verdict(null, 0.4, 'typesafe:jev-1.13.0'),
            ],
            $this->judge->decide(
                [new Subject(3, ['name' => 'rewe']), new Subject(7, ['name' => 'cinema'])],
                'payee',
                $this->necessity(),
                $context,
            ),
        );
    }

    public function testAsksAFlagJustLikeItsQuestion(): void
    {
        $question = new Question('Is `%s` a subscription?', 'A subscription.', 'No subscription.');

        $this->driver
            ->expects(self::once())
            ->method('answer')
            ->with(['payee_3' => ['name' => 'netflix']], ['payee_3' => $question->about('payee_3')], null)
            ->willReturn(['payee_3' => 0.8]);

        self::assertEquals(
            [3 => new Verdict(true, 0.8, 'typesafe:jev-1.13.0')],
            $this->judge->decide([new Subject(3, ['name' => 'netflix'])], 'payee', new Flag($question, 0.6)),
        );
    }

    public function testDecidesByAConsumersOwnRule(): void
    {
        // a rule SmartJudge does not ship: yes only when both of its questions are likely
        $both = new class implements Rule {
            #[Override]
            public function questions(): array
            {
                return [
                    'monthly' => new Question('Is `%s` paid monthly?', 'Monthly.', 'Not monthly.'),
                    'fixed' => new Question('Is `%s` a fixed amount?', 'Fixed.', 'Varies.'),
                ];
            }

            #[Override]
            public function decide(array $probabilities, string $source): Verdict
            {
                $probability = $probabilities['monthly'] * $probabilities['fixed'];

                return new Verdict($probability >= 0.5 ? 'both' : 'not both', $probability, $source);
            }
        };

        $this->driver
            ->method('answer')
            ->willReturn(['payee_1__monthly' => 0.9, 'payee_1__fixed' => 0.8, 'payee_2__monthly' => 0.9, 'payee_2__fixed' => 0.1]);

        $verdicts = $this->judge->decide([new Subject(1, []), new Subject(2, [])], 'payee', $both);

        self::assertSame(['both', 'not both'], [$verdicts[1]->value, $verdicts[2]->value]);
        self::assertSame('typesafe:jev-1.13.0', $verdicts[1]->source);
    }

    public function testBatchesBySubjectsAndAsksAllTheirQuestionsTogether(): void
    {
        $batches = [];

        $this->driver
            ->expects(self::exactly(2))
            ->method('answer')
            ->willReturnCallback(static function (array $facts, array $questions) use (&$batches): array {
                $batches[] = array_keys($questions);

                return array_map(static fn (Question $question): float => 0.5, $questions);
            });

        $verdicts = $this->judge->decide(
            [new Subject('a', []), new Subject('b', []), new Subject('c', [])],
            'payee',
            $this->necessity(),
            batchSize: 2,
        );

        self::assertSame(
            [
                ['payee_a__essential', 'payee_a__discretionary', 'payee_b__essential', 'payee_b__discretionary'],
                ['payee_c__essential', 'payee_c__discretionary'],
            ],
            $batches,
        );
        self::assertSame(['a', 'b', 'c'], array_keys($verdicts));
    }

    public function testFingerprintsWhatIsAskedTogetherWithItsDriver(): void
    {
        self::assertEquals(
            Fingerprint::of([$this->necessity()], 'typesafe:jev-1.13.0'),
            $this->judge->fingerprint([$this->necessity()]),
        );
    }

    private function necessity(): Choice
    {
        return new Choice(
            [
                new Option('essential', new Question('Is `%s` essential?', 'Essential.', 'Not essential.')),
                new Option('discretionary', new Question('Is `%s` discretionary?', 'Discretionary.', 'Not discretionary.')),
            ],
            0.6,
        );
    }
}
