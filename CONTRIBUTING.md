# Contributing

This repository is a monorepo. It contains the shared GrumPHP configuration `pluswerk/grumphp-config` in the root and several packages in [`packages/`](packages). This page explains how to work on it and how releases are published.

Please open issues and pull requests in [andersundsehr/grumphp-config](https://github.com/andersundsehr/grumphp-config). The repositories of the single packages are read-only copies.

## Repository layout

| Path | Content |
| --- | --- |
| `composer.json` | The Composer plugin `pluswerk/grumphp-config` |
| `src/Composer/Plugin.php` | Creates and updates the configuration files in projects |
| `src/RectorSettings.php`, `src/FractorSettings.php` | Rule sets and skips used by the generated `rector.php` and `fractor.php` |
| `src/VersionUtility.php` | Reads the lowest PHP and TYPO3 version from the project's `composer.json` |
| `grumphp.yml` | The shared GrumPHP configuration that projects import; also the configuration for this repository |
| `tests/`, `phpunit.xml.dist` | PHPUnit tests of the root, e.g. for the git hooks that GrumPHP creates from `grumphp.yml` |
| `rector.php`, `fractor.php`, `phpstan.neon` | Templates that the plugin copies into projects; also the configuration for this repository. The packages do not commit `rector.php` and `fractor.php`, they get them from the plugin |
| `phpstan-disallowed-calls.neon` | Debug calls that PHPStan reports in projects (`spaze/phpstan-disallowed-calls`); `extra.phpstan.includes` in `composer.json` lets `phpstan/extension-installer` load it automatically |
| `phpstan-baseline.neon` | PHPStan baseline of this repository for CI runs with TYPO3 packages; new projects get an empty baseline instead |
| `phpstan-baseline-without-typo3.neon` | PHPStan baseline of this repository for CI runs without TYPO3 packages |
| `.gitattributes` | Files that are not part of the Composer package (`export-ignore`), e.g. `packages/`, the baselines and the scripts |
| `logo.txt` | ASCII logo that GrumPHP prints after a run |
| `packages/*` | The packages, each with its own `composer.json`, `grumphp.yml` and tool configuration |
| `grumphp-run-all.sh` | Runs GrumPHP in every package and in the root |
| `split.sh` | Publishes the packages to their own repositories |
| `.github/workflows/run-grumphp-test.yml` | CI and code split |
| `.ddev/` | DDEV configuration for development |

## Development setup

### With DDEV (recommended)

```bash
ddev start
ddev composer install
ddev grumphp run
```

The custom command `ddev grumphp` runs GrumPHP of the root project in the web container, e.g. `ddev grumphp run` or `ddev grumphp git:init`.

DDEV uses PHP 8.4 and sets `COMPOSER_ROOT_VERSION=dev-main`, so Composer can resolve the packages from `packages/`.

The custom command `ddev here` runs a command inside the container in the same directory you are in on the host. This is handy inside a package:

```bash
cd packages/rector-p
ddev here composer install
ddev here vendor/bin/grumphp run
```

### Without DDEV

You need PHP 8.2 or newer, Composer, git, `file` and, for `grumphp-run-all.sh`, `rsync`.

```bash
COMPOSER_ROOT_VERSION=dev-main composer install
vendor/bin/grumphp run
```

The repository also contains a `docker-compose.yml` with the `ghcr.io/pluswerk/php-dev` image (PHP 8.2) as an older alternative to DDEV.

## How the packages are wired

- The root `composer.json` has a `path` repository for `packages/*`. It requires the packages with `^12.0.0` and, in `require-dev`, with `dev-main as 12.0.0`, so the root always uses the local package code.
- Each package has its own `composer.json` with two `path` repositories: `../../` for `pluswerk/grumphp-config` and `../*` for the other packages. `pluswerk/grumphp-config` is copied into the package's `vendor/` (`"symlink": false`), so every package is checked with the current configuration from the root.
- After `composer update` in a package, its `post-update-cmd` scripts run `composer bump -D`, `composer normalize` and remove `vendor/pluswerk/grumphp-config/vendor` and `vendor/pluswerk/grumphp-config/packages` from the copy.
- All packages share one version number. `extra.branch-alias` maps `dev-main` to the current major version (`12.0.x-dev` or `12.0-dev`).

## Running the checks

Root only:

```bash
vendor/bin/grumphp run
```

One package:

```bash
cd packages/grumphp-xliff-task
COMPOSER_ROOT_VERSION=dev-main composer update
vendor/bin/grumphp run
```

The copy of `pluswerk/grumphp-config` in the package's `vendor/` is only refreshed by `composer update`. Run it again after you change files in the root.

`rector-p` has PHPUnit tests in `packages/rector-p/tests`. Its `grumphp.yml` adds the `phpunit` task, so `vendor/bin/grumphp run` in `packages/rector-p` runs them too. PHPUnit is also a dev dependency of the root, because PHPStan and Rector in the root check the test files.

### Tests of the root

The root has PHPUnit tests in `tests/`:

```bash
vendor/bin/phpunit
```

The root `grumphp.yml` does not add the `phpunit` task, because projects import this file. CI runs the tests in its own step.

`DdevGitHookTest` checks the `git_hook_variables` in `grumphp.yml`:

- GrumPHP creates the hooks with `git:init` from the real `grumphp.yml`.
- Each test commits in a new git repository, in the main worktree or in a linked worktree.
- Fake `ddev` and `php` commands log their arguments, so the test sees how the hook starts GrumPHP.
- `PATH` contains only these fakes, `git` and `cat`. A real `ddev` on your computer does not change the result.

Run the tests whenever you change the `git_hook_variables`. Each part of the shell code must have a test that fails without it: remove the part, run the tests and make sure that one fails.

### Mutation testing (rector-p)

[Infection](https://infection.github.io/) checks whether the `rector-p` tests notice changes in the code. Run it whenever you change code or tests in `packages/rector-p`:

```bash
cd packages/rector-p
composer require --dev --no-plugins --no-scripts infection/infection
vendor/bin/infection --threads=max --only-covering-test-cases
composer remove --dev --no-plugins --no-scripts infection/infection
```

- Infection needs PHP 8.3 or newer and PCOV or Xdebug. It is not a dev dependency in `composer.json`, because `rector-p` still supports PHP 8.2.
- Use the default settings and do not pass `--with-uncovered`.
- `--only-covering-test-cases` runs only the test methods that cover a mutation, instead of the whole test class.
- `infection.json5` sets the minimum score (`minMsi`); the run fails below it. If your change raises the score, raise `minMsi` to the new value, rounded down. Do not lower it.
- The escaped mutants are listed in `var/infection/infection.log`. Add or sharpen tests for real gaps. Equivalent or cosmetic mutants do not need to be killed. With `--log-verbosity=all` the log also shows why each killed mutant failed; check it after adding tests, a test that fails for its own reason kills mutants by mistake.
- `infection.json5` lists mutations that Infection does not run at all: cosmetic ones (style tags, the time estimate, the duration printed with `-vvv`) and ones that can not change the behaviour. Each entry has a comment why. The summary counts them as `Ignored`, they do not count in the score. Keep the entries narrow, one line or one method for one mutator, because an entry also hides the useful mutations it matches.
- Most tests in `PartialCommandTest` use a fake Rector, `tests/Fixture/bin/rector`. It answers in milliseconds instead of about a second and logs its arguments. Only the `testRealRector…` tests, `testListsAllFilesWithoutTouchingTheResultCacheOfRector` (it fills Rector's cache) and `RectorPBinaryTest` start the real Rector. Use the fake for new tests of rector-p's own logic and the real Rector only for tests about working with Rector.
- The tests run `PartialCommand` in the test process. Code in `src/` must not call `exit()`; throw `StopException` instead, otherwise Infection cannot test it.
- Every test class declares what it tests with `#[CoversClass]` (`phpunit.xml.dist` requires it). Then a mutation in a small class like `Cache` only runs its fast unit tests, and not the slower `PartialCommandTest`. Tests that only start the binary use `#[CoversNothing]`.

Everything:

```bash
./grumphp-run-all.sh
```

The script refreshes the copy of the root configuration in every package with `rsync`, runs `composer update` and `vendor/bin/grumphp run` in every package and finally in the root. It stops at the first failure.

### PHPStan configuration of the projects

There are two ways to bring PHPStan configuration into the projects:

- `extra.phpstan.includes` in `composer.json` (like `phpstan-disallowed-calls.neon`): `phpstan/extension-installer` loads it in every project, also in existing ones after the next update. PHPStan loads it before the `phpstan.neon` of the project, so the project can add to it or replace parameters with `!`. Use it for rules that should apply everywhere.
- The template `phpstan.neon`: the plugin copies it only if the project has none, so changes reach only new projects. Use it for settings the project owns. `level` and `paths` must be here: PHPStan reads them only from the project config and its includes, so `andersundsehr/phpstan-git-files` (it sets `paths`) is included here and can not move to `extra.phpstan`. A file that is included both ways stops PHPStan with "This file is included multiple times".

### PHPStan baselines

CI checks the root in two variants:

- **with TYPO3:** CI installs `saschaegerer/phpstan-typo3` and `ssch/typo3-rector` with `typo3/cms-core` `^13.4` or `^14.0` and uses `phpstan-baseline.neon`. Because `typo3/cms-core` is then in `require`, Rector also applies the TYPO3 sets, e.g. every exception needs a code.
- **without TYPO3:** CI replaces `phpstan-baseline.neon` with `phpstan-baseline-without-typo3.neon`.

If you change code that has entries in the baselines, update both files. To reproduce the TYPO3 variant locally, run the command from the workflow and do not commit the resulting changes to `composer.json`:

```bash
TYPO3_SKIP_ASSET_PUBLISH=true composer req saschaegerer/phpstan-typo3 ssch/typo3-rector "typo3/cms-core:^14.0" --no-progress --no-scripts
```

## Continuous integration

The workflow [`run-grumphp-test.yml`](.github/workflows/run-grumphp-test.yml) runs on every push and pull request, every night and on demand:

| Job | What it does |
| --- | --- |
| `test` | `vendor/bin/grumphp run` and `vendor/bin/phpunit` in the root for PHP 8.2 to 8.6, each without TYPO3 and with TYPO3 13 and 14 |
| `test-packages` | `vendor/bin/grumphp run` in the packages listed in the job matrix for PHP 8.2 to 8.6 |
| `code-split` | After both test jobs succeeded, on pushes to `main` and on tags: publishes every package with `split.sh` |

The jobs run in the `kanti/buildy` container images.

## Commit messages

Commit subjects use the TYPO3 prefixes `[FEATURE]`, `[BUGFIX]` and `[TASK]`. The `git_commit_message` task allows at most 120 characters per line.

## Rector version

Rector removes deprecated classes, constants and rules in minor releases. For example, Rector 2.9.0 removed `SetList::PHP_*` and rules that `ssch/typo3-rector` still used. So `composer.json` and `packages/rector-p/composer.json` allow only one minor version of Rector, with a tilde constraint such as `~2.8.0`. Treat a new minor version of Rector as a breaking change: raise the constraint in its own pull request and check it with and without TYPO3.

## Releases

1. Merge the changes into `main` and wait for a green CI run.
2. Create a GitHub release in this repository with a new tag. Tags have no prefix, e.g. `11.2.1`. Use semantic versioning: a new major version for breaking changes.
3. The tag push starts CI. After the tests, the `code-split` job publishes every package and releases it with the same version.

Older major versions are maintained on branches such as `11.x`. A new major version needs:

1. the new version in the root `composer.json` (`^12.0.0`, `dev-main as 12.0.0`) and in `extra.branch-alias` of every package,
2. before its first release: the branch `<major>.x` in every package repository, created by hand from the newest commit of the previous major version, and set as the default branch there. `split.sh` pushes only when the package changed, so it does not create the branch on its own,
3. a maintenance branch `<previous major>.x` in this repository, if the previous major version still gets releases.

Every maintenance branch with `split.sh` (`10.x` and newer) needs the branch handling described below. The `code-split` job runs `split.sh` from the tagged commit, so a fix of the script has to be ported to every maintenance branch that still gets releases.

### What `split.sh` does

For each package, `split.sh`

1. clones `github.com/andersundsehr/<package>` into `tmp/<package>`,
2. checks out the branch `<major>.x` of the package repository, e.g. `12.x`. Every major version has its own branch there, `main` is no longer used. The major version comes from the `dev-main as <version>` aliases in `require-dev` of the root `composer.json`. A new branch starts at the last `<major>.*` tag, or at the default branch for a new major version,
3. copies `packages/<package>` into the clone, without files ignored by `.gitignore`, and removes ignored files that are still in the package repository,
4. commits the changes with the subject, author and date of the current monorepo commit and a link to it, and pushes them to that branch,
5. if the current monorepo commit has a tag and the package commit has none yet: tags the package commit with the same name, pushes the tag and creates a GitHub release for it.

The script needs the GitHub CLI `gh`, `jq` and a token with write access to the package repositories in `GH_TOKEN`. In CI the token comes from the secret `ACCESS_TOKEN` of the `code split` environment. Locally you can split a single package:

```bash
GH_TOKEN=… ./split.sh rector-p
```

The script sets the global git user only if none is configured. Delete `tmp/` afterwards.

## Adding a package

1. Create `packages/<name>/` with `composer.json`, `README.md`, `LICENSE.txt`, `.gitignore`, `grumphp.yml`, `phpstan.neon` and `phpstan-baseline.neon`. Copy the `.gitignore` from an existing package: `rector.php` and `fractor.php` are not committed, the plugin creates them from the templates in the root on `composer install`. Copy the `repositories`, `require-dev`, `config`, `extra.branch-alias` and `scripts` sections from an existing package and set `support.source` to the package folder.
2. Require the package in the root `composer.json`: in `require` with the current version constraint and in `require-dev` as `dev-main as <version>`.
3. Add the package to the `rm -rf` lists in `grumphp-run-all.sh`.
4. Add the package to the `test-packages` and `code-split` matrices in the workflow.
5. Create the empty repository `andersundsehr/<name>` on GitHub, give the split token access and register the package on Packagist.
6. Add the package to the table in the root [README](README.md#packages-in-this-repository).
