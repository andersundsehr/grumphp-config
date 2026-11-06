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
6. Every test class needs `#[CoversClass]` for the class it tests (`#[CoversNothing]` for tests that only start the binary). This keeps Infection fast.

### Keeping Infection fast and its score honest

Lessons from setting up mutation testing for rector-p:

- **`#[CoversClass]` plus `--only-covering-test-cases` make the run fast.** Without `#[CoversClass]` PHPUnit credits every line a test executes to that test. `PartialCommandTest` starts real Rector runs and uses `Cache`, `ChunkConfig` and `TimeHelper`, so every mutation in those small classes also ran the slow command tests. Without `--only-covering-test-cases` Infection runs the whole test class instead of the covering test methods. With both, a full run took about 20 minutes on 4 cores (363 mutations); without them it was on track for about 40 minutes.
- **Only code that runs in the PHPUnit process can kill mutations.** A test that starts `rector-p` as its own process (`RectorPBinaryTest`) is useful, but it never sees mutated code. That is why `PartialCommandTest` runs the command in-process and why `src/` must not call `exit()`.
- **Check `var/infection/summary.log` for `Skipped`.** Infection skips a mutation if even the fastest covering test takes longer than `timeout` in `infection.json5`, and skipped mutations do not count in the score. A high score with many skipped mutations means nothing.
- **Coverage is limited to `src/`** by the `<source>` element in `phpunit.xml.dist`. Without it PCOV also measures Rector and PHPStan, and every test becomes much slower.
- **Infection writes its logs only at the end.** If you stop a run early, you get no list of escaped mutations. Run it in the background and wait.
