[![Packagist](https://img.shields.io/packagist/v/pluswerk/grumphp-config.svg?style=flat-square)](https://packagist.org/packages/pluswerk/grumphp-config)
[![Packagist](https://img.shields.io/packagist/l/pluswerk/grumphp-config.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)
[![grumphp](https://github.com/andersundsehr/grumphp-config/actions/workflows/run-grumphp-test.yml/badge.svg)](https://github.com/andersundsehr/grumphp-config/actions/workflows/run-grumphp-test.yml)

# pluswerk/grumphp-config

A ready-to-use [GrumPHP](https://github.com/phpro/grumphp) setup for PHP projects, with extra support for TYPO3.

One `composer require` gives your project:

- a git `pre-commit` and `commit-msg` hook that checks every commit,
- linting for PHP, JSON, YAML, XML and XLIFF,
- coding style checks (PSR-12),
- static analysis with PHPStan (level 8 with a baseline),
- automated refactoring checks with Rector and, for TYPO3, Fractor,
- `composer validate`, `composer normalize` and `composer audit`,
- configuration files that you own and can adjust per project.

The shared configuration is updated with `composer update`, so all projects get new checks and version updates (PHP, TYPO3, Rector, PHPStan) the same way.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Included tasks](#included-tasks)
- [Configuration](#configuration)
- [Rector](#rector)
- [Fractor](#fractor)
- [PHPStan](#phpstan)
- [TYPO3 projects](#typo3-projects)
- [Updating](#updating)
- [Packages in this repository](#packages-in-this-repository)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- PHP 8.2, 8.3, 8.4, 8.5 or 8.6
- Composer 2.1 or newer
- git; the project must be a git repository, because file lists and hooks come from git
- the `file` command line tool, which the BOM check uses
- `shell_exec()` must be allowed in PHP CLI; the generated `rector.php`, `fractor.php` and the PHPStan extension use it to call `git ls-files`

## Installation

```bash
composer require --dev pluswerk/grumphp-config
```

Composer asks whether it may run the Composer plugins that come with this package. Allow them, or add them to your `composer.json` beforehand:

```json
{
  "config": {
    "allow-plugins": {
      "a9f/fractor-extension-installer": true,
      "ergebnis/composer-normalize": true,
      "phpro/grumphp": true,
      "phpstan/extension-installer": true,
      "pluswerk/grumphp-config": true
    }
  }
}
```

### What the installation does

`pluswerk/grumphp-config` is a Composer plugin. After `composer install` and `composer update` it creates the following files in your project root, **if they do not exist yet**. Existing files are never overwritten.

| File | Content |
| --- | --- |
| `grumphp.yml` | Imports `vendor/pluswerk/grumphp-config/grumphp.yml` and lists all `convention.*` parameters with their defaults |
| `rector.php` | Rector configuration based on `PLUS\GrumPHPConfig\RectorSettings` (see [Rector](#rector)) |
| `fractor.php` | Fractor configuration based on `PLUS\GrumPHPConfig\FractorSettings` (see [Fractor](#fractor)) |
| `phpstan.neon` | PHPStan level 8, includes the baseline and [`andersundsehr/phpstan-git-files`](packages/phpstan-git-files) |
| `phpstan-baseline.neon` | An empty baseline for existing PHPStan errors (see [PHPStan](#phpstan)) |

`rector.php` and `fractor.php` are also created after `composer dump-autoload`.

In addition, on every `composer install` and `composer update` the plugin

- adds `convention.*` parameters that are new in the installed version to your `grumphp.yml`, as long as the first import in that file is `vendor/pluswerk/grumphp-config/grumphp.yml`. Your own values stay unchanged. The file is rewritten in this case, so YAML comments in it are lost.
- installs `saschaegerer/phpstan-typo3` and `ssch/typo3-rector` as dev dependencies in TYPO3 projects (see [TYPO3 projects](#typo3-projects)).
- removes the outdated settings `extra.grumphp.config-default-path` and `extra.pluswerk/grumphp-config` from your `composer.json`.

GrumPHP itself installs the git hooks during `composer install`.

Commit the generated files.

## Usage

GrumPHP runs automatically on `git commit`. The `pre-commit` hook checks the staged files and the `commit-msg` hook checks the commit message.

Run all checks on the whole project, for example in CI:

```bash
vendor/bin/grumphp run
```

Run only some tasks:

```bash
vendor/bin/grumphp run --tasks=phpstan,rector
```

Install the git hooks again, for example after cloning without running `composer install`:

```bash
vendor/bin/grumphp git:init
```

### Automatic fixes

Some tasks can fix what they find: `composer_normalize`, `phpcs` (with `phpcbf`), `rector` and `fractor`. When one of them fails, GrumPHP asks whether it should apply the fixes. Check the result and stage the changed files before you commit again.

### DDEV

The git hooks detect [DDEV](https://ddev.com/). If you commit on the host, the project has a `.ddev` folder and `ddev` is installed, the hook runs GrumPHP inside the web container (`ddev exec FORCE_COLOR=1 php vendor/bin/grumphp …`). This way the checks use the PHP version and extensions of the container. Inside the container, and in projects without DDEV, the hook calls `php` directly.

## Included tasks

The shared [`grumphp.yml`](grumphp.yml) enables these tasks. All tasks run, even if an earlier one fails (`stop_on_failure: false`). Each task may run for up to 240 seconds.

| Task | What it checks |
| --- | --- |
| `composer` | `composer validate` (without the publish check, not strict) |
| `composer_validate_autoload` | PSR-4/PSR-0 autoload mapping (`composer dump-autoload --optimize --dry-run --strict-psr`) |
| `composer_normalize` | `composer.json` is normalized with [ergebnis/composer-normalize](https://github.com/ergebnis/composer-normalize) (2 spaces) and the lock file is up to date |
| `git_commit_message` | Subject and body lines have at most 120 characters; a capital first letter is not required |
| `jsonlint` | JSON syntax and duplicate keys |
| `phpcs` | PHP_CodeSniffer with the PSR-12 standard; only errors count, warnings are ignored |
| `phpstan` | PHPStan with your `phpstan.neon` |
| `phplint` | PHP syntax ([php-parallel-lint](https://github.com/php-parallel-lint/PHP-Parallel-Lint)) |
| `xmllint` | XML syntax |
| `yamllint` | YAML syntax |
| `xlifflint` | XLIFF files, see [`pluswerk/grumphp-xliff-task`](packages/grumphp-xliff-task) |
| `securitychecker_composeraudit` | `composer audit` on `composer.lock`, including dev packages; runs on every commit; abandoned packages are reported but do not fail |
| `plus_bom_fixer` | No UTF-8 byte order mark (BOM) in PHP, CSS, SCSS, LESS, JSON, SQL, YAML and TXT files, see [`pluswerk/grumphp-bom-task`](packages/grumphp-bom-task); runs first |
| `rector` | `rector process --dry-run` with your `rector.php`; triggered by changed PHP files |
| `fractor` | `fractor process --dry-run` with your `fractor.php`, see [`andersundsehr/grumphp-fractor-task`](packages/grumphp-fractor-task); triggered by changed PHP files |

## Configuration

Keep the import of the shared file at the top of your `grumphp.yml` and change the project-specific parts below it.

### Convention parameters

Most settings can be changed with `convention.*` parameters in your `grumphp.yml`:

```yaml
imports:
  - { resource: vendor/pluswerk/grumphp-config/grumphp.yml }
parameters:
  convention.phpcslint_ignore_pattern: ['/^src\/Legacy\//']
  convention.security_checker_blocking: false
```

| Parameter | Default | Meaning |
| --- | --- | --- |
| `convention.process_timeout` | `240` | Maximum run time per task in seconds |
| `convention.security_checker_blocking` | `true` | `false`: findings of `composer audit` are shown but do not fail the run |
| `convention.jsonlint_ignore_pattern` | `[]` | Paths that `jsonlint` skips |
| `convention.xmllint_ignore_pattern` | `[]` | Paths that `xmllint` skips |
| `convention.yamllint_ignore_pattern` | `[]` | Paths that `yamllint` skips |
| `convention.phpcslint_ignore_pattern` | `[]` | Paths that `phpcs` skips |
| `convention.phpcslint_exclude` | `[]` | Sniffs that `phpcs` does not check, e.g. `['Generic.Files.LineLength']` |
| `convention.xlifflint_ignore_pattern` | `[]` | Paths that `xlifflint` skips |
| `convention.rector_enabled` | `true` | `false` disables the `rector` task |
| `convention.rector_config` | `'rector.php'` | Path to the Rector configuration |
| `convention.rector_clear-cache` | `false` | `true` runs Rector without its cache |
| `convention.rector_ignore_pattern` | `[]` | Paths that `rector` skips |
| `convention.fractor_enable` | `true` | `false` disables the `fractor` task |
| `convention.fractor_config` | `'fractor.php'` | Path to the Fractor configuration |
| `convention.fractor_clear-cache` | `false` | `true` runs Fractor without its cache |
| `convention.fractor_ignore_pattern` | `[]` | Paths that `fractor` skips |
| `convention.phpstan_level` | `~` (null) | PHPStan level; `null` uses the level from `phpstan.neon` |

Ignore patterns are either plain strings, which match any path that contains them, or regular expressions delimited with `/`, such as `'/^tests\/fixtures\//'`. Paths are relative to the project root.

### Changing a task

To change other options of a task, define the task again under `grumphp.tasks`:

```yaml
grumphp:
  tasks:
    phpcs:
      standard: "PSR12"
      warning_severity: 0
      tab_width: 4
      exclude: "%convention.phpcslint_exclude%"
      ignore_patterns: "%convention.phpcslint_ignore_pattern%"
      report: "summary"
```

Your definition **replaces** the shared definition of that task completely; the options are not merged. Copy all options from the [shared `grumphp.yml`](grumphp.yml) that you want to keep.

### Disabling a task

```yaml
grumphp:
  tasks:
    phpcs:
      metadata:
        enabled: false
```

For Rector and Fractor use `convention.rector_enabled: false` and `convention.fractor_enable: false`.

### Adding tasks

Add more [GrumPHP tasks](https://github.com/phpro/grumphp/blob/v2.x/doc/tasks.md) under `grumphp.tasks` in your `grumphp.yml`, for example `phpunit`. They run together with the shared tasks.

## Rector

The generated `rector.php` calls `RectorSettings::apply()` with the Rector configuration builder and returns it:

```php
return RectorSettings::apply(RectorConfig::configure())
    ->withSkip([
        __DIR__ . '/src/Example',
    ]);
```

`RectorSettings::apply()`

- checks all PHP files that are tracked in git,
- runs in parallel and caches results in `var/cache/rector`,
- imports class names and removes unused imports,
- uses the prepared sets `deadCode`, `codeQuality`, `codingStyle`, `typeDeclarations` and `privatization`,
- uses the PHP sets (`withPhpSets()`) for the lowest PHP version that your `composer.json` allows in `require.php` (for `^8.2` that is PHP 8.2). Without a PHP requirement the PHP version that runs Rector is used,
- in TYPO3 projects: uses the TYPO3 sets up to the lowest TYPO3 version that your `composer.json` allows in `require.typo3/cms-core`, plus the TYPO3 `CODE_QUALITY` and `GENERAL` sets. TYPO3 10 to 14 are supported, `dev-main` counts as 14,
- skips the rules from [`RectorSettings::skip()`](#rectorsettingsskip).

The file belongs to your project. Call more methods of the builder after `apply()`:

- `withSkip()`, `withSets()` and `withRules()` add to the configuration.
- `withPaths()` and `withCache()` replace it.
- `withPhpSets()` can only be called once. To use your own PHP version, call `RectorSettings::apply(RectorConfig::configure(), phpSets: false)` and then `withPhpSets(php83: true)`.
- `RectorSettings::apply(RectorConfig::configure(), typo3: false)` leaves out the TYPO3 sets.

### Older `rector.php` files

Older `rector.php` files configure Rector with `RectorSettings::sets()` and `RectorSettings::skip()` and keep working in version 12. `sets()` returns the prepared sets above, `SetList::PHP_VERSION_BASED_SET` and the TYPO3 sets; `sets(false)` is no longer supported and triggers a deprecation, the sets always contain all upgrades. To switch to `apply()`, replace your `rector.php` with the [template](rector.php) and move your own skips, sets and rules into it.

### `RectorSettings::skip()`

Skips rules that change code in ways we do not want:

| Rule | Reason |
| --- | --- |
| `ExplicitNullableObjectInstanceRector` | Keep `$object === null`, `if ($object)` and `$object && …` instead of `instanceof` checks |
| `ArrayExplicitBoolCompareRector` | Keep `if ($array)` instead of `if ($array !== [])` |
| `IssetOnPropertyObjectToPropertyExistsRector` | Keep `isset($this->x)` |
| `PrivatizeFinalClassPropertyRector` for `*/Model/*` | Model properties stay `protected` |

In TYPO3 projects it also skips:

- `SensitiveConstantNameRector`,
- all files in `Configuration/ExtensionBuilder`,
- name importing in `ClassAliasMap.php`,
- `declare(strict_types=1)` in `ext_emconf.php`.

Skip entries for rules that no longer exist in the installed Rector version, or that Rector marks as deprecated, are removed automatically. This keeps the configuration working across Rector versions.

### `RectorSettings::getLevel(int $level = 9999)`

Deprecated, it will be removed in version 13. Rector has its own levels, e.g. `withTypeCoverageLevel()`. Helper for introducing Rector step by step. It returns the number given with `--level=N` on the Rector command line, or `$level` if there is none. It removes the option from the arguments, so Rector does not reject it, and passes the value to Rector's parallel worker processes through the environment variable `RECTOR_CLI_OPTION_LEVEL`. Use the return value in your `rector.php` wherever Rector expects a level.

### Deprecated methods

`RectorSettings::setsTypo3()` and `RectorSettings::skipTypo3()` return empty arrays. The TYPO3 sets and skips are part of `sets()` and `skip()` now. Older `rector.php` files may still call them; you can remove these calls. Both are marked as `@deprecated`, so PHPStan reports these calls (see [PHPStan](#phpstan)).

### Introducing Rector in large projects

Use [`andersundsehr/rector-p`](packages/rector-p), which is installed with this package. It runs Rector file by file and asks before it changes a file:

```bash
vendor/bin/rector-p
```

## Fractor

[Fractor](https://github.com/andreaswolf/fractor) refactors non-PHP files in TYPO3 projects, such as TypoScript, FlexForms and YAML. The generated `fractor.php`

- calls `FractorSettings::apply()` with the Fractor configuration builder and returns it, like `rector.php`,
- checks all files that are tracked in git,
- uses the TYPO3 sets up to the lowest TYPO3 version that your `composer.json` allows (`FractorSettings::sets()`),
- formats TypoScript with 2 spaces, indented conditions and conditions closed with `[end]` (`FractorSettings::options()`).

Call more methods of the builder after `apply()`, e.g. `withSkip()`. `withOptions()` replaces the options of `apply()`. Older `fractor.php` files with `FractorSettings::sets(true)` keep working.

In projects without TYPO3 there are no sets. Because Fractor needs at least one rule, `FractorSettings::rules()` then adds a single TypoScript rule, which has no effect on projects without TypoScript.

## PHPStan

The generated `phpstan.neon`:

```neon
includes:
  - phpstan-baseline.neon
  - vendor/andersundsehr/phpstan-git-files/extension.php

parameters:
  level: 8
  reportUnmatchedIgnoredErrors: false
```

- [`andersundsehr/phpstan-git-files`](packages/phpstan-git-files) adds all PHP files tracked in git to the analysis, so you do not need to maintain `paths`.
- [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) loads installed PHPStan extensions automatically, e.g. `bnf/phpstan-psr-container` and, in TYPO3 projects, `saschaegerer/phpstan-typo3`.
- [`phpstan/phpstan-deprecation-rules`](https://github.com/phpstan/phpstan-deprecation-rules) is installed with this package and reports the use of deprecated classes, methods, functions and constants, e.g. of TYPO3 or Rector. Fix them before the next update removes them, or add them to `phpstan-baseline.neon` with `vendor/bin/phpstan --generate-baseline`.
- [`phpstan/phpstan-phpunit`](https://github.com/phpstan/phpstan-phpunit) is installed with this package. PHPStan understands PHPUnit assertions such as `assertInstanceOf()` and `assertNotNull()` and the types of mock objects, and checks some assertions, e.g. `assertSame(true, …)` should be `assertTrue(…)`.
- [`spaze/phpstan-disallowed-calls`](https://github.com/spaze/phpstan-disallowed-calls) is installed with this package and reports debug output that must not be committed: `var_dump()`, `print_r()` and `var_export()` (allowed with `true` as the second argument), `dump()`, `dd()`, `debug()`, `debug_zval_dump()`, `debug_print_backtrace()`, `xdebug_break()`, `xdebug_var_dump()`, `phpinfo()`, `DebuggerUtility::var_dump()`, `DebugUtility::*()` of TYPO3 and `VarDumper::dump()` of Symfony. The list is in [`phpstan-disallowed-calls.neon`](phpstan-disallowed-calls.neon); it is loaded automatically. Your `phpstan.neon` is loaded after it:
  - add your own rules to `parameters.disallowedFunctionCalls`, `disallowedStaticCalls` etc., they are added to the list,
  - replace the list with a `!` after the name, e.g. `disallowedFunctionCalls!: []` and `disallowedStaticCalls!: []` switch it off.
- `phpstan-baseline.neon` holds known errors, so you can start at level 8 in an existing project and fix old errors over time.

Write all current errors into the baseline:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

To use a different level only for the GrumPHP task, set `convention.phpstan_level`.

## TYPO3 projects

If `typo3/cms-core` is installed, the plugin also requires these packages as dev dependencies and installs them:

- [`saschaegerer/phpstan-typo3`](https://github.com/sascha-egerer/phpstan-typo3)
- [`ssch/typo3-rector`](https://github.com/sabbelasichon/typo3-rector)

The version constraints come from the `require-typo3` section of this package's [`composer.json`](composer.json). A package is left alone if it is already installed in a matching version or as a `dev-` version.

The Rector and Fractor sets follow the lowest TYPO3 version that `require.typo3/cms-core` in your `composer.json` allows, see [Rector](#rector) and [Fractor](#fractor).

## Updating

```bash
composer update pluswerk/grumphp-config --with-all-dependencies
```

- Changes to the shared `grumphp.yml` take effect immediately.
- New `convention.*` parameters are added to your `grumphp.yml` automatically.
- `rector.php`, `fractor.php`, `phpstan.neon` and `phpstan-baseline.neon` are not updated, because they belong to your project. To get the current templates, delete the files and run `composer update` again; then move your own changes back into the new files and regenerate the baseline.

### From 11 to 12

```bash
composer require --dev pluswerk/grumphp-config:^12.0.0 --with-all-dependencies
```

- Version 12 installs [`phpstan/phpstan-deprecation-rules`](#phpstan), so PHPStan reports the use of deprecated code.
- Version 12 installs [`spaze/phpstan-disallowed-calls`](#phpstan) with a list of debug functions, so PHPStan reports calls like `var_dump()` or `DebuggerUtility::var_dump()`.
- Version 12 installs [`phpstan/phpstan-phpunit`](#phpstan), so PHPStan understands PHPUnit tests better and checks some assertions.
- Remove the calls of `RectorSettings::setsTypo3()` and `RectorSettings::skipTypo3()` from your `rector.php`, see [Deprecated methods](#deprecated-methods).
- Fix the other findings, or add them to `phpstan-baseline.neon` with `vendor/bin/phpstan analyse --generate-baseline`.

## Packages in this repository

This is a monorepo. Besides the configuration it contains the following packages. They are installed together with `pluswerk/grumphp-config`, but they can also be used on their own.

| Package | Description |
| --- | --- |
| [`pluswerk/grumphp-bom-task`](packages/grumphp-bom-task) | GrumPHP task that finds files with a UTF-8 BOM, plus the `fixbom` command |
| [`pluswerk/grumphp-xliff-task`](packages/grumphp-xliff-task) | GrumPHP task that lints XLIFF translation files |
| [`andersundsehr/grumphp-fractor-task`](packages/grumphp-fractor-task) | GrumPHP task for Fractor |
| [`andersundsehr/phpstan-git-files`](packages/phpstan-git-files) | PHPStan extension that analyses all PHP files tracked in git |
| [`andersundsehr/rector-p`](packages/rector-p) | Runs Rector file by file and asks before each change, like `git add -p` |

Each package is published from here to its own read-only repository at `github.com/andersundsehr/<package>`. Please open issues and pull requests in [this repository](https://github.com/andersundsehr/grumphp-config/issues).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the development setup, tests and the release process.

## License

`pluswerk/grumphp-config` and most packages in this repository are licensed under the [GNU LGPL 3.0 or later](LICENSE.txt). `andersundsehr/rector-p` is licensed under the GPL 2.0 or later.

# with ♥️ from anders und sehr GmbH

> If something did not work 😮  
> or you appreciate this Extension 🥰 let us know.

> We are hiring https://www.andersundsehr.com/karriere/
