[![Packagist](https://img.shields.io/packagist/v/pluswerk/grumphp-bom-task.svg?style=flat-square)](https://packagist.org/packages/pluswerk/grumphp-bom-task)
[![Packagist](https://img.shields.io/packagist/l/pluswerk/grumphp-bom-task.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)

# pluswerk/grumphp-bom-task

A [GrumPHP](https://github.com/phpro/grumphp) task that fails if a file starts with a UTF-8 byte order mark (BOM), and the `fixbom` command that removes it.

Most editors do not show a BOM, but it causes real problems: in a PHP file it is sent as output before `<?php`, and `declare(strict_types=1)` is no longer the first statement. JSON parsers and shell scripts often fail on it as well.

> This task is part of [`pluswerk/grumphp-config`](https://github.com/andersundsehr/grumphp-config). If you use that package, the task is already installed and configured.

## Requirements

- PHP 8.2 or newer
- GrumPHP 2
- the `file` command line tool

## Installation

```bash
composer require --dev pluswerk/grumphp-bom-task
```

Register the extension and the task in your `grumphp.yml`:

```yaml
grumphp:
  tasks:
    plus_bom_fixer:
      triggered_by: [php, css, scss, less, json, sql, yml, txt]
  extensions:
    - PLUS\GrumPHPBomTask\ExtensionLoader
```

## Options

**triggered_by**

*Default: `[php, css, scss, less, json, sql, yml, txt]`*

File extensions to check. Extend the list if you need more, for example `yaml`, `xlf`, `html` or `typoscript`.

The task also accepts GrumPHP's [`metadata`](https://github.com/phpro/grumphp/blob/v2.x/doc/tasks.md#metadata) options, e.g. `priority`.

## How it works

The task runs with `vendor/bin/grumphp run` and in the `pre-commit` hook; in the hook it checks only the staged files.

For each file with a matching extension it calls `file <path>` and looks for `BOM` in the output. If the `file` command is missing or fails, the file counts as a file without BOM.

The task does not change files. If it finds a BOM, it fails and prints the affected files together with a command that fixes them:

```text
src/Example.php has BOM and should be fixed
you can use this to fix them:
./vendor/bin/fixbom 'src/Example.php'
```

## `fixbom`

```bash
vendor/bin/fixbom file1 [file2 ...]
```

Removes a UTF-8 BOM from the start of each given file. The file permissions and the group are kept. Files without a BOM stay unchanged; files that do not exist are reported and skipped.

## Upgrading from the `AUS` version

Older versions were published as `andersundsehr/grumphp-bom-task` with the namespace `AUS`. Rename the task and the extension in your `grumphp.yml`:

| Old | New |
| --- | --- |
| `aus_bom_fixer` | `plus_bom_fixer` |
| `AUS\GrumPHPBomTask\ExtensionLoader` | `PLUS\GrumPHPBomTask\ExtensionLoader` |

If your `grumphp.yml` still lists tasks and extensions under `parameters:`, move them under `grumphp:`; GrumPHP 1.0 and newer only read them there.

## Contributing

This package is developed in the monorepo [andersundsehr/grumphp-config](https://github.com/andersundsehr/grumphp-config/tree/main/packages/grumphp-bom-task). Please open issues and pull requests there.

## License

[LGPL-3.0-or-later](LICENSE.txt)
