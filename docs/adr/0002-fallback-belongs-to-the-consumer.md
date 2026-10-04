# The fallback belongs to the consumer

When a driver is unavailable, SmartJudge throws `JudgeUnavailable` and the consumer falls back to its own way of deciding; SmartJudge has no fallback chain. A rule-based judge (such as eBud's basic recurring judge, which decides from numbers) cannot read question text, so it cannot be a driver, and a chain of two AI drivers is not needed while there is only one driver.

## Considered Options

- A `FallbackJudge(primary, fallback)` in the package: rejected, it only works when the fallback is another driver.
- Rule-based judges as drivers: rejected, a driver must answer any question the consumer writes.
