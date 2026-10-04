<?php

declare(strict_types=1);

namespace Potato\SmartJudge\Infrastructure\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Override;
use Potato\SmartJudge\Domain\Driver;
use Potato\SmartJudge\Domain\Question;

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

    private ClientInterface $client;

    public function __construct(
        private string $apiKey,
        private string $model,
        private string $baseUrl = self::BASE_URL,
        ?ClientInterface $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    #[Override]
    public function name(): string
    {
        return 'typesafe:' . $this->model;
    }

    #[Override]
    public function answer(array $facts, array $questions): array
    {
        $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            'json' => [
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
            ],
        ]);

        /** @var array{answers: array<string, array{noul: float|int}>} $body */
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $probabilities = [];

        foreach (array_keys($questions) as $id) {
            $probabilities[$id] = (float) $body['answers'][$id][self::QUESTION_TYPE];
        }

        return $probabilities;
    }
}
