[![Packagist](https://img.shields.io/packagist/v/andersundsehr/grumphp-fractor-task.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/grumphp-fractor-task)
[![Packagist](https://img.shields.io/packagist/l/andersundsehr/grumphp-fractor-task.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)

# andersundsehr/grumphp-fractor-task

A [GrumPHP](https://github.com/phpro/grumphp) task for [Fractor](https://github.com/andreaswolf/fractor). Fractor does for non-PHP files what Rector does for PHP: it upgrades and refactors TypoScript, FlexForms, YAML and other files in TYPO3 projects.

The task runs Fractor in dry-run mode and fails if Fractor would change a file.

> This task is part of [`pluswerk/grumphp-config`](https://github.com/andersundsehr/grumphp-config). If you use that package, the task, Fractor and a `fractor.php` are already installed and configured.

## Requirements

- PHP 8.2 or newer
- GrumPHP 2
- Fractor, e.g. [`a9f/typo3-fractor`](https://packagist.org/packages/a9f/typo3-fractor)

## Installation

```bash
composer require --dev andersundsehr/grumphp-fractor-task a9f/typo3-fractor
```

Register the extension and the task in your `grumphp.yml`:

```yaml
grumphp:
  tasks:
    fractor:
      config: null
      triggered_by: ['php']
      ignore_patterns: []
      clear_cache: true
      no_diffs: false
  extensions:
    - Andersundsehr\GrumPHPFractorTask\ExtensionLoader
```

Fractor needs a configuration file, by default `fractor.php` in the project root. A minimal example:

```php
<?php

declare(strict_types=1);

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;

return FractorConfiguration::configure()
    ->withPaths([__DIR__ . '/packages/'])
    ->withSets([Typo3LevelSetList::UP_TO_TYPO3_13]);
```

## Options

**config**

*Default: `null`*

Path to the Fractor configuration file. With `null`, Fractor uses `fractor.php` in the project root.

**triggered_by**

*Default: `['php']`*

File extensions that start the task. In the `pre-commit` hook only matching staged files are passed to Fractor. Fractor itself mostly works on other file types, so add the extensions your project uses, for example `typoscript`, `tsconfig`, `xml` or `yaml`, if commits that only touch those files should be checked as well.

**ignore_patterns**

*Default: `[]`*

Paths that the task does not pass to Fractor: plain strings that a path must contain, or regular expressions delimited with `/`. Use this to skip files such as test fixtures.

**clear_cache**

*Default: `true`*

Run Fractor without its cache (`--clear-cache`). Set it to `false` for faster runs.

**no_diffs**

*Default: `false`*

Do not show the diffs of the changes Fractor would make (`--no-diffs`).

## How it works

The task runs

```bash
fractor process --dry-run --ansi --no-progress-bar [--config=…] [--clear-cache] [--no-diffs] [files…]
```

- In the `pre-commit` hook it passes the staged files that match `triggered_by` and are not excluded by `ignore_patterns`.
- With `vendor/bin/grumphp run` it passes no files, so Fractor checks the paths from its configuration.
- If Fractor would change something, the task fails. GrumPHP then offers to run the same command without `--dry-run`, which applies the changes.

## Contributing

This package is developed in the monorepo [andersundsehr/grumphp-config](https://github.com/andersundsehr/grumphp-config/tree/main/packages/grumphp-fractor-task). Please open issues and pull requests there.

## License

[LGPL-3.0-or-later](LICENSE.txt)

# with ♥️ from anders und sehr GmbH

> If something did not work 😮  
> or you appreciate this Extension 🥰 let us know.

> We are hiring https://www.andersundsehr.com/karriere/
