<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Application;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Application\Judge;
use Potato\SmartJudge\Domain\Context;
use Potato\SmartJudge\Domain\InvalidQuestion;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Subject;
use Potato\SmartJudge\Infrastructure\Drivers\TypeSafe;
use Psr\Http\Message\RequestInterface;

final class JudgeTest extends TestCase
{
    private MockHandler $responses;

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    private Judge $judge;

    #[Override]
    protected function setUp(): void
    {
        $this->responses = new MockHandler();
        $stack = HandlerStack::create($this->responses);
        $stack->push(Middleware::history($this->history));

        $this->judge = new Judge(
            new TypeSafe('secret-key', 'jev-1.13.0', 'https://jev.example.org/v1', new Client(['handler' => $stack])),
        );
    }

    public function testAsksTypeSafeOneQuestionPerSubjectAndReturnsProbabilitiesByKey(): void
    {
        $this->responses->append(new Response(200, [], json_encode([
            'model' => 'jev-1.13.0',
            'answers' => [
                'transaction_3' => ['type' => 'noul', 'noul' => 0.95],
                'transaction_7' => ['type' => 'noul', 'noul' => 0.1],
            ],
        ], JSON_THROW_ON_ERROR)));

        $probabilities = $this->judge->ask(
            [
                new Subject(3, ['description' => 'netflix', 'frequency' => 'monthly']),
                new Subject(7, ['description' => 'bakery', 'frequency' => 'weekly']),
            ],
            'transaction',
            new Question('Is `%s` a recurring transaction?', 'A commitment that repeats.', 'One-off spending.'),
        );

        self::assertSame([3 => 0.95, 7 => 0.1], $probabilities);
        self::assertCount(1, $this->history);

        $request = $this->history[0]['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://jev.example.org/v1/systemone', (string) $request->getUri());
        self::assertSame('Bearer secret-key', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(
            [
                'state' => [
                    'transaction_3' => ['description' => 'netflix', 'frequency' => 'monthly'],
                    'transaction_7' => ['description' => 'bakery', 'frequency' => 'weekly'],
                ],
                'model' => 'jev-1.13.0',
                'questions' => [
                    'transaction_3' => [
                        'type' => 'noul',
                        'instructions' => 'Is `transaction_3` a recurring transaction?',
                        'criteria' => ['true' => 'A commitment that repeats.', 'false' => 'One-off spending.'],
                    ],
                    'transaction_7' => [
                        'type' => 'noul',
                        'instructions' => 'Is `transaction_7` a recurring transaction?',
                        'criteria' => ['true' => 'A commitment that repeats.', 'false' => 'One-off spending.'],
                    ],
                ],
            ],
            json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testKeepsNestedFactsOfAPairAsOneSubject(): void
    {
        $this->responses->append(new Response(200, [], json_encode([
            'answers' => ['pair_a-b' => ['noul' => 0.8]],
        ], JSON_THROW_ON_ERROR)));

        $probabilities = $this->judge->ask(
            [new Subject('a-b', ['first' => ['description' => 'netflix'], 'second' => ['description' => 'NETFLIX.COM']])],
            'pair',
            new Question('Do `first` and `second` of `%s` describe the same payee?', 'Same payee.', 'Different payees.'),
        );

        self::assertSame(['a-b' => 0.8], $probabilities);

        /** @var array{state: array<string, mixed>} $body */
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            ['pair_a-b' => ['first' => ['description' => 'netflix'], 'second' => ['description' => 'NETFLIX.COM']]],
            $body['state'],
        );
    }

    public function testSendsTheContextOnceUnderItsFixedKey(): void
    {
        $this->responses->append(new Response(200, [], json_encode([
            'answers' => ['transaction_1' => ['noul' => 0.2], 'transaction_2' => ['noul' => 0.3]],
        ], JSON_THROW_ON_ERROR)));

        $this->judge->ask(
            [new Subject(1, ['description' => 'netflix']), new Subject(2, ['description' => 'spotify'])],
            'transaction',
            new Question('Given the `context`, is `%s` recurring?', 'Recurring.', 'One-off.'),
            new Context(['currency' => 'euro']),
        );

        /** @var array{state: array<string, mixed>} $body */
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            [
                'context' => ['currency' => 'euro'],
                'transaction_1' => ['description' => 'netflix'],
                'transaction_2' => ['description' => 'spotify'],
            ],
            $body['state'],
        );
    }

    public function testFillsOnlyThePlaceholderAndKeepsOtherPercentSigns(): void
    {
        $this->responses->append(new Response(200, [], json_encode([
            'answers' => ['transaction_1' => ['noul' => 0.6]],
        ], JSON_THROW_ON_ERROR)));

        $this->judge->ask(
            [new Subject(1, ['description' => 'rent'])],
            'transaction',
            new Question('Is `%s` more than 50% of the income?', 'More.', 'Less.'),
        );

        /** @var array{questions: array<string, array{instructions: string}>} $body */
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Is `transaction_1` more than 50% of the income?', $body['questions']['transaction_1']['instructions']);
    }

    /**
     * @param list<Subject> $subjects
     */
    #[DataProvider('invalidQuestionsDataProvider')]
    public function testRejectsMistakesInWhatIsAskedBeforeAnyRequest(array $subjects, Question $question, int $batchSize): void
    {
        try {
            $this->judge->ask($subjects, 'transaction', $question, batchSize: $batchSize);
            self::fail('Expected InvalidQuestion was not thrown.');
        } catch (InvalidQuestion $exception) {
            self::assertInstanceOf(LogicException::class, $exception);
        }

        self::assertCount(0, $this->history);
    }

    /**
     * @return array<string, array{list<Subject>, Question, int}>
     */
    public static function invalidQuestionsDataProvider(): array
    {
        $question = new Question('Is `%s` recurring?', 'Recurring.', 'One-off.');
        $subjects = [new Subject(1, []), new Subject(2, [])];

        return [
            'no placeholder' => [$subjects, new Question('Is it recurring?', 'Recurring.', 'One-off.'), 20],
            'two placeholders' => [$subjects, new Question('Is `%s` like `%s`?', 'Alike.', 'Different.'), 20],
            // 1 and '1' are the same key in PHP, and would answer for each other
            'duplicate keys' => [[new Subject(1, []), new Subject('1', [])], $question, 20],
            'a later duplicate key' => [[...$subjects, new Subject(3, []), new Subject(2, [])], $question, 1],
            'batch size of zero' => [$subjects, $question, 0],
        ];
    }
}
