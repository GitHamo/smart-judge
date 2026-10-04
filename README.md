# SmartJudge

Asks an AI model yes/no questions about subjects you describe, and gives back a probability per subject, or a decision made from those probabilities. Plain PHP on Guzzle, without a framework. For Laravel, use `potato/smart-judge-laravel` on top.

The words used here (subject, facts, context, question, criteria, driver, rule, verdict) are defined in [CONTEXT.md](CONTEXT.md).

## Install

```sh
composer require potato/smart-judge
```

Requires PHP 8.3.

## Config

A judge asks one driver. SmartJudge ships the TypeSafe driver, which asks a TypeSafe model such as Jev:

```php
use Potato\SmartJudge\Application\Judge;
use Potato\SmartJudge\Infrastructure\Drivers\TypeSafe;

$judge = new Judge(new TypeSafe(
    apiKey: getenv('TYPESAFE_API_KEY'),
    model: 'jev-1.13.0',
    baseUrl: 'https://api.typesafe.ai/v1', // the default
    client: null,                          // your own Guzzle client, e.g. with a proxy; a new one by default
    timeout: 5,                            // seconds, the default
));
```

The driver retries twice, 250 ms apart, on 429, 529 and connection errors. Its name includes the model, e.g. `typesafe:jev-1.13.0`, so a model change shows in verdicts and fingerprints. To use another provider, implement `Potato\SmartJudge\Domain\Driver`.

## Usage

Describe each subject by your own key and its facts, write the question with a `%s` placeholder for the subject, and give the criteria of "yes" and "no":

```php
use Potato\SmartJudge\Domain\Context;
use Potato\SmartJudge\Domain\Flag;
use Potato\SmartJudge\Domain\JudgeUnavailable;
use Potato\SmartJudge\Domain\Question;
use Potato\SmartJudge\Domain\Subject;

$subjects = [
    new Subject(41, ['description' => 'NETFLIX.COM', 'tags' => ['subscriptions']]),
    new Subject(42, ['description' => 'Lunch at Joe\'s', 'tags' => ['food']]),
];

$question = new Question(
    'Is `%s` a subscription the household could cancel?',  // `%s` becomes the id, e.g. `transaction_41`
    'A service paid for on a schedule that can be ended.',  // what "yes" means
    'A one-off purchase, a necessity or a contract that cannot be ended.', // what "no" means
);

// facts that hold for every subject, sent once; question text refers to them as `context`
$context = new Context(['country' => 'Germany']);

try {
    // probability of "yes" per subject key: [41 => 0.94, 42 => 0.03]
    $probabilities = $judge->ask($subjects, 'transaction', $question, $context);

    // or a decision per subject key: Verdict(value: true, probability: 0.94, source: 'typesafe:jev-1.13.0')
    $verdicts = $judge->decide($subjects, 'transaction', new Flag($question, threshold: 0.7), $context);
} catch (JudgeUnavailable $exception) {
    // the driver gave no usable answer: fall back to your own way of deciding
    // $exception->driver, $exception->status and $exception->reason say why
}
```

- Subjects are asked in batches of 20; pass `batchSize:` to change that.
- `Choice` picks the most likely of several `Option`s, the first listed on ties and none below the threshold. Add your own rule by implementing `Potato\SmartJudge\Domain\Rule`.
- `$judge->fingerprint([$rule])` changes whenever the rules, their questions or the driver change, so you can tell when stored verdicts are stale.
- Mistakes in what you ask, such as a question without the placeholder, a choice without options or two subjects with the same key, throw `InvalidQuestion` before any request. Never catch it as an outage.

## Privacy

Everything in the facts, the context and the question text is sent to the driver's provider. SmartJudge does not filter it: you are responsible for what you send. Leave out what the model does not need to judge, such as names, account numbers, exact amounts or dates.

## Tests

```sh
composer install
vendor/bin/phpunit
```
