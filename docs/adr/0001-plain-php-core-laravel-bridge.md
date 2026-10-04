# Plain PHP core, Laravel bridge for cache and log

SmartJudge is two packages. `potato/smart-judge` is plain PHP and depends only on Guzzle: it asks and decides, and its driver retries and times out, because that is part of making one request. `potato/smart-judge-laravel` holds the answer cache, the log, the config, and the skip after a failure (no request to a driver for 60 seconds after it was unavailable), because those are policies of the app that uses SmartJudge, and the app controls its own cache store and log channel.

## Consequences

- A plain PHP consumer gets retries but no skip after a failure; every request to a driver that is down waits for its timeout. Moving the skip into the core later only adds code, so this was accepted.
- The core has no PSR-16 or PSR-3 dependency. Caching and logging are decorators around the driver in the Laravel package.
