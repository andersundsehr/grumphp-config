# Notes for coding agents

This repository is a monorepo: the shared GrumPHP configuration in the root and several packages in `packages/`. [CONTRIBUTING.md](CONTRIBUTING.md) describes the layout, the checks and the release process. Read it first.

## Checks

- Run `vendor/bin/grumphp run` in the root and in every package you changed, see [Running the checks](CONTRIBUTING.md#running-the-checks).
- Commit subjects use the TYPO3 prefixes `[FEATURE]`, `[BUGFIX]` and `[TASK]`.

## Tests and mutation testing in rector-p

When you change code or tests in `packages/rector-p`:

1. Run the tests: `vendor/bin/phpunit` in `packages/rector-p` (they also run as part of `vendor/bin/grumphp run` there).
2. Run Infection as described in [Mutation testing](CONTRIBUTING.md#mutation-testing-rector-p):
   - with `--only-covering-test-cases`,
   - with the default settings, never with `--with-uncovered`.
3. The score must stay at or above `minMsi` in `packages/rector-p/infection.json5`. If it went up, raise `minMsi` to the new value, rounded down. Never lower it.
4. Read the escaped mutants in `var/infection/infection.log`. Write or sharpen tests for real gaps. Equivalent or purely cosmetic mutants do not need to be killed.
5. Do not call `exit()` in `src/`; throw `StopException`. The tests run `PartialCommand` in the test process.
