<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Application;

use Override;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Application\Judge;
use Potato\SmartJudge\Domain\Context;
use Potato\SmartJudge\Domain\Driver;
use Potato\SmartJudge\Domain\JudgeUnavailable;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Subject;

final class JudgeBatchingTest extends TestCase
{
    private Driver&MockObject $driver;

    private Judge $judge;

    private Question $question;

    #[Override]
    protected function setUp(): void
    {
        $this->driver = $this->createMock(Driver::class);
        $this->judge = new Judge($this->driver);
        $this->question = new Question('Is `%s` recurring?', 'Recurring.', 'One-off.');
    }

    public function testAsksOncePerBatchOfTheGivenSizeAndReturnsAllProbabilitiesByKey(): void
    {
        $context = new Context(['currency' => 'euro']);
        $batches = [];

        $this->driver
            ->expects(self::exactly(3))
            ->method('answer')
            ->willReturnCallback(static function (array $facts, array $questions, ?Context $given) use (&$batches, $context): array {
                self::assertSame($context, $given);
                $batches[] = array_keys($facts);

                return array_map(static fn (Question $question): float => 0.5, $questions);
            });

        $probabilities = $this->judge->ask($this->subjects(['a', 'b', 'c', 'd', 'e']), 'subject', $this->question, $context, 2);

        self::assertSame([['subject_a', 'subject_b'], ['subject_c', 'subject_d'], ['subject_e']], $batches);
        self::assertSame(['a' => 0.5, 'b' => 0.5, 'c' => 0.5, 'd' => 0.5, 'e' => 0.5], $probabilities);
    }

    public function testAsksInBatchesOfTwentyByDefault(): void
    {
        $sizes = [];

        $this->driver
            ->expects(self::exactly(2))
            ->method('answer')
            ->willReturnCallback(static function (array $facts, array $questions) use (&$sizes): array {
                $sizes[] = \count($facts);

                return array_map(static fn (Question $question): float => 0.5, $questions);
            });

        $this->judge->ask($this->subjects(range(1, 25)), 'subject', $this->question);

        self::assertSame([20, 5], $sizes);
    }

    public function testMapsEachProbabilityBackToItsSubjectsKey(): void
    {
        $this->driver
            ->expects(self::once())
            ->method('answer')
            ->with(
                ['subject_7' => ['n' => 7], 'subject_3' => ['n' => 3]],
                [
                    'subject_7' => new Question('Is `subject_7` recurring?', 'Recurring.', 'One-off.'),
                    'subject_3' => new Question('Is `subject_3` recurring?', 'Recurring.', 'One-off.'),
                ],
                null,
            )
            ->willReturn(['subject_3' => 0.3, 'subject_7' => 0.7]);

        self::assertSame(
            [7 => 0.7, 3 => 0.3],
            $this->judge->ask([new Subject(7, ['n' => 7]), new Subject(3, ['n' => 3])], 'subject', $this->question),
        );
    }

    public function testADriverThatLeavesOutAQuestionIsUnavailable(): void
    {
        $this->driver->method('name')->willReturn('custom:v1');
        $this->driver->method('answer')->willReturn(['subject_a' => 0.5]);

        try {
            $this->judge->ask($this->subjects(['a', 'b']), 'subject', $this->question);
            self::fail('Expected JudgeUnavailable was not thrown.');
        } catch (JudgeUnavailable $exception) {
            self::assertSame('custom:v1', $exception->driver);
            self::assertNull($exception->status);
            self::assertSame('Gave no answer for question "subject_b".', $exception->reason);
        }
    }

    public function testAsksNothingWithoutSubjects(): void
    {
        $this->driver->expects(self::never())->method('answer');

        self::assertSame([], $this->judge->ask([], 'subject', $this->question));
    }

    /**
     * @param list<int|string> $keys
     *
     * @return list<Subject>
     */
    private function subjects(array $keys): array
    {
        return array_map(static fn (int|string $key): Subject => new Subject($key, ['key' => $key]), $keys);
    }
}
