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
4. Read the escaped mutants in `var/infection/infection.log`. Write or sharpen tests for real gaps. Equivalent or purely cosmetic mutants do not need to be killed; if they are cosmetic output, add a narrow ignore with a comment to `infection.json5` (see below).
5. Do not call `exit()` in `src/`; throw `StopException`. The tests run `PartialCommand` in the test process.
6. Every test class needs `#[CoversClass]` for the class it tests (`#[CoversNothing]` for tests that only start the binary). This keeps Infection fast.
7. New tests of `PartialCommand` use the fake Rector in `tests/Fixture/bin/rector` (the default in `PartialCommandTest`). Call `useRealRector()` only when the test is about working with the real Rector.

### Keeping Infection fast and its score honest

Lessons from setting up mutation testing for rector-p:

- **`#[CoversClass]` plus `--only-covering-test-cases` make the run fast.** Without `#[CoversClass]` PHPUnit credits every line a test executes to that test. `PartialCommandTest` starts real Rector runs and uses `Cache`, `ChunkConfig` and `TimeHelper`, so every mutation in those small classes also ran the slow command tests. Without `--only-covering-test-cases` Infection runs the whole test class instead of the covering test methods. With both, a full run took about 20 minutes on 4 cores (363 mutations) while every test still used the real Rector; without them it was on track for about 40 minutes.
- **Only code that runs in the PHPUnit process can kill mutations.** A test that starts `rector-p` as its own process (`RectorPBinaryTest`) is useful, but it never sees mutated code. That is why `PartialCommandTest` runs the command in-process and why `src/` must not call `exit()`. If a behaviour depends on what another process left behind, prepare that state in the test and run rector-p in-process afterwards: `testListsAllFilesWithoutTouchingTheResultCacheOfRector` lets the real Rector fill its result cache first. `RectorPBinaryTest` checks the same with separate processes, like a user.
- **Check `var/infection/summary.log` for `Skipped`.** Infection skips a mutation if even the fastest covering test takes longer than `timeout` in `infection.json5`, and skipped mutations do not count in the score. A high score with many skipped mutations means nothing.
- **Coverage is limited to `src/`** by the `<source>` element in `phpunit.xml.dist`. Without it PCOV also measures Rector and PHPStan, and every test becomes much slower.
- **Infection writes its logs only at the end.** If you stop a run early, you get no list of escaped mutations. Run it in the background and wait.
- **A fake Rector makes the command tests fast.** A real Rector run takes about a second, and `PartialCommandTest` needs several per test. `tests/Fixture/bin/rector` answers in milliseconds: a file with `list($a, $b)` has changes, `function broken(` fails, `fail on apply` fails when the changes are applied. It logs every call to `rector-calls.log` in the project, `ProjectFixture::rectorCalls()` reads it. Only the three `testRealRector…` tests, `testListsAllFilesWithoutTouchingTheResultCacheOfRector` and `RectorPBinaryTest` start the real Rector. Together with the ignores below, a full Infection run went from about 21 to about 4.5 minutes on 4 cores.
- **Ignored mutations are not run at all.** `infection.json5` ignores cosmetic mutations (style tags, the time estimate, the duration printed with `-vvv`) and a few that can not change the behaviour, each with a comment. A regex in `ignoreSourceCodeByRegex` matches a whole line of the original code, so it hides every mutation of that mutator on the line, also useful ones. Keep entries narrow (one mutator, one line or one method) and prefer a test where one is possible, like the regex for the time estimate in `testFindsRectorDistPhpAndPassesItToEveryRectorRun`. Never ignore whole classes or mutators to raise the score. Three escaped mutants are left on purpose: `$matches[1]` → `$matches[0]` in `parseChunkConfig()` is equivalent (`(int)'2/3'` is 2), and the two style tag mutants in `$countPart` share their line with useful mutations.
