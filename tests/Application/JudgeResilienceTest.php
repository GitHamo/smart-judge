<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Tests\Application;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Potato\SmartJudge\Application\Judge;
use Potato\SmartJudge\Domain\JudgeUnavailable;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Subject;
use Potato\SmartJudge\Infrastructure\Drivers\TypeSafe;
use Psr\Http\Message\RequestInterface;
use Throwable;

final class JudgeResilienceTest extends TestCase
{
    private const string URL = 'https://jev.example.org/v1/systemone';

    private MockHandler $responses;

    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    private array $history = [];

    private Judge $judge;

    #[Override]
    protected function setUp(): void
    {
        $this->responses = new MockHandler();
        $stack = HandlerStack::create($this->responses);
        $stack->push(Middleware::history($this->history));

        $this->judge = new Judge(new TypeSafe(
            'secret-key',
            'jev-1.13.0',
            'https://jev.example.org/v1',
            new Client(['handler' => $stack]),
            timeout: 7,
        ));
    }

    public function testSendsTheConfiguredTimeout(): void
    {
        $this->responses->append($this->answer(0.5));

        $this->ask();

        self::assertSame(7.0, $this->history[0]['options']['timeout']);
    }

    #[DataProvider('retriedStatusesDataProvider')]
    public function testRetriesWhenRateLimitedOrOverloaded(int $status): void
    {
        $this->responses->append(new Response($status), new Response($status), $this->answer(1));

        self::assertSame([1 => 1.0], $this->ask());
        self::assertCount(3, $this->history);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function retriedStatusesDataProvider(): array
    {
        return ['rate limited' => [429], 'overloaded' => [529]];
    }

    public function testRetriesWhenTypeSafeCannotBeReached(): void
    {
        $this->responses->append($this->connectionError(), $this->answer(0.3));

        self::assertSame([1 => 0.3], $this->ask());
        self::assertCount(2, $this->history);
    }

    public function testWaits250MillisecondsBetweenAttempts(): void
    {
        $this->responses->append(new Response(429), new Response(429), $this->answer(1));

        $start = hrtime(true);
        $this->ask();

        self::assertGreaterThanOrEqual(500_000_000, hrtime(true) - $start);
    }

    /**
     * @param list<Response|Throwable> $responses
     */
    #[DataProvider('failuresDataProvider')]
    public function testIsUnavailableWhenTypeSafeGivesNoUsableAnswer(
        array $responses,
        int $expectedRequests,
        ?int $expectedStatus,
        string $expectedReason,
    ): void {
        $this->responses->append(...$responses);

        try {
            $this->ask();
            self::fail('Expected JudgeUnavailable was not thrown.');
        } catch (JudgeUnavailable $exception) {
            self::assertSame('typesafe:jev-1.13.0', $exception->driver);
            self::assertSame($expectedStatus, $exception->status);
            self::assertSame($expectedReason, $exception->reason);
            self::assertStringContainsString($expectedReason, $exception->getMessage());
        }

        self::assertCount($expectedRequests, $this->history);
    }

    /**
     * @return array<string, array{list<Response|Throwable>, int, int|null, string}>
     */
    public static function failuresDataProvider(): array
    {
        $json = static fn (mixed $body): Response => new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR));
        $noAnswer = 'Response has no answer for question "transaction_1".';

        return [
            // a client error will not pass on a retry
            'invalid key' => [[new Response(401)], 1, 401, 'Responded with status 401.'],
            'validation failed' => [[new Response(422)], 1, 422, 'Responded with status 422.'],
            'server error' => [[new Response(500)], 1, 500, 'Responded with status 500.'],
            'still overloaded after retries' => [
                [new Response(529), new Response(529), new Response(529)],
                3,
                529,
                'Responded with status 529.',
            ],
            'still unreachable after retries' => [
                [self::connectionError(), self::connectionError(), self::connectionError()],
                3,
                null,
                'Could not be reached: timed out',
            ],
            // e.g. cURL error 60: the certificate of the server cannot be verified
            'transport failed before a response' => [
                [new RequestException('cURL error 60: SSL certificate problem', new Request('POST', self::URL))],
                1,
                null,
                'Could not be reached: cURL error 60: SSL certificate problem',
            ],
            'body is no JSON' => [[new Response(200, [], 'oops')], 1, 200, $noAnswer],
            'no answers' => [[$json(['model' => 'jev-1.13.0'])], 1, 200, $noAnswer],
            'other question answered' => [[$json(['answers' => ['foo' => ['noul' => 1]]])], 1, 200, $noAnswer],
            'answer is no number' => [[$json(['answers' => ['transaction_1' => ['noul' => 'yes']]])], 1, 200, $noAnswer],
        ];
    }

    public function testLetsErrorsOtherThanTransportThrough(): void
    {
        // a bug is no outage of TypeSafe: it must not be hidden as one
        $this->responses->append(new LogicException('bug'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('bug');

        $this->ask();
    }

    #[DataProvider('clampedDataProvider')]
    public function testClampsProbabilitiesBetweenZeroAndOne(float $answer, float $expected): void
    {
        $this->responses->append($this->answer($answer));

        self::assertSame([1 => $expected], $this->ask());
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function clampedDataProvider(): array
    {
        return ['above one' => [1.2, 1.0], 'below zero' => [-0.1, 0.0]];
    }

    /**
     * @return array<int|string, float>
     */
    private function ask(): array
    {
        return $this->judge->ask(
            [new Subject(1, ['description' => 'netflix'])],
            'transaction',
            new Question('Is `%s` recurring?', 'Recurring.', 'One-off.'),
        );
    }

    private function answer(float|int $probability): Response
    {
        return new Response(200, [], json_encode(
            ['answers' => ['transaction_1' => ['type' => 'noul', 'noul' => $probability]]],
            JSON_THROW_ON_ERROR,
        ));
    }

    private static function connectionError(): ConnectException
    {
        return new ConnectException('timed out', new Request('POST', self::URL));
    }
}
