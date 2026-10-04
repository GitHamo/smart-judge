<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Infrastructure\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Override;
use Potato\SmartJudge\Domain\Context;
use Potato\SmartJudge\Domain\Driver;
use Potato\SmartJudge\Domain\JudgeUnavailable;
use Potato\SmartJudge\Domain\Question;
use Psr\Http\Message\ResponseInterface;

/**
 * Asks a TypeSafe model, such as Jev, yes/no ("noul") questions.
 *
 * @see https://docs.typesafe.ai/api
 */
final readonly class TypeSafe implements Driver
{
    private const string BASE_URL = 'https://api.typesafe.ai/v1';
    private const string ENDPOINT = '/systemone';
    private const string QUESTION_TYPE = 'noul';
    private const float TIMEOUT_SECONDS = 5;
    private const int RETRIES = 2;
    private const int RETRY_DELAY_MILLISECONDS = 250;
    // rate limited and overloaded; any other failed response will not pass on a retry
    private const array RETRY_STATUSES = [429, 529];

    private ClientInterface $client;

    public function __construct(
        private string $apiKey,
        private string $model,
        private string $baseUrl = self::BASE_URL,
        ?ClientInterface $client = null,
        private float $timeout = self::TIMEOUT_SECONDS,
    ) {
        $this->client = $client ?? new Client();
    }

    #[Override]
    public function name(): string
    {
        return 'typesafe:' . $this->model;
    }

    #[Override]
    public function answer(array $facts, array $questions, ?Context $context): array
    {
        if (null !== $context) {
            $facts = [Context::KEY => $context->facts, ...$facts];
        }

        $response = $this->send([
            'state' => $facts,
            'model' => $this->model,
            'questions' => array_map(
                static fn (Question $question): array => [
                    'type' => self::QUESTION_TYPE,
                    'instructions' => $question->text,
                    'criteria' => ['true' => $question->yes, 'false' => $question->no],
                ],
                $questions,
            ),
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $answers = \is_array($body) && \is_array($body['answers'] ?? null) ? $body['answers'] : [];
        $probabilities = [];

        foreach (array_keys($questions) as $id) {
            $probability = \is_array($answers[$id] ?? null) ? $answers[$id][self::QUESTION_TYPE] ?? null : null;

            if (!is_numeric($probability)) {
                throw new JudgeUnavailable(
                    $this->name(),
                    $response->getStatusCode(),
                    \sprintf('Response has no answer for question "%s".', $id),
                );
            }

            $probabilities[$id] = min(1.0, max(0.0, (float) $probability));
        }

        return $probabilities;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JudgeUnavailable when no attempt got a successful response
     */
    private function send(array $payload): ResponseInterface
    {
        for ($attempt = 0; ; $attempt++) {
            if ($attempt > 0) {
                usleep(self::RETRY_DELAY_MILLISECONDS * 1000);
            }

            $mayRetry = $attempt < self::RETRIES;

            try {
                $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . self::ENDPOINT, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                    'timeout' => $this->timeout,
                    'http_errors' => false,
                ]);
            } catch (TransferException $exception) {
                // only transport errors, e.g. also cURL error 60 when the certificate of the server cannot be verified;
                // other errors are no outage and pass through
                if ($mayRetry && $exception instanceof ConnectException) {
                    continue;
                }

                throw new JudgeUnavailable(
                    $this->name(),
                    $exception instanceof RequestException ? $exception->getResponse()?->getStatusCode() : null,
                    'Could not be reached: ' . $exception->getMessage(),
                    $exception,
                );
            }

            $status = $response->getStatusCode();

            if ($mayRetry && \in_array($status, self::RETRY_STATUSES, true)) {
                continue;
            }

            if ($status >= 400) {
                throw new JudgeUnavailable($this->name(), $status, \sprintf('Responded with status %d.', $status));
            }

            return $response;
        }
    }
}
