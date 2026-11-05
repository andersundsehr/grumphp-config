[![Packagist](https://img.shields.io/packagist/v/andersundsehr/rector-p.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/rector-p)
[![Packagist](https://img.shields.io/packagist/l/andersundsehr/rector-p.svg?style=flat-square)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

# andersundsehr/rector-p

Run [Rector](https://getrector.com/) file by file and decide for each file whether to apply the changes, like `git add -p`.

## Why?

Introducing Rector in a large, old project often produces more changes than anyone can review at once. `rector-p` lets you go through the project one file at a time: you see the diff for a file, apply it, skip it or adjust things and try again. It remembers which files are done, so you can stop and continue later.

[![asciicast](https://asciinema.org/a/671145.png)](https://asciinema.org/a/671145)

> `rector-p` is part of [`pluswerk/grumphp-config`](https://github.com/andersundsehr/grumphp-config). If you use that package, it is already installed.

## Requirements

- PHP 8.2 or newer
- Rector 2 with a `rector.php`; it must define `paths` unless you pass paths on the command line

## Installation

```bash
composer require --dev andersundsehr/rector-p
```

## Usage

```bash
vendor/bin/rector-p
```

Only some directories or files:

```bash
vendor/bin/rector-p src/Controller/
vendor/bin/rector-p src/Controller/MyController.php src/Controller/MyOtherController.php
```

### What happens

1. `rector-p` loads `rector.php` from the current directory and collects the files from its `paths`, or from the paths you pass as arguments. The files are sorted by name.
2. For each file it runs `rector process --dry-run <file>`, which shows the diff.
3. If Rector has nothing to change, the file is marked as done. Otherwise you choose:

   | Answer | Effect |
   | --- | --- |
   | `execute` (default) | Apply the changes and mark the file as done |
   | `skip-file` | Leave the file as it is; you are asked again on the next run |
   | `retry` | Run the dry run again, e.g. after you edited the file or `rector.php` |
   | `quit` | Stop; files marked as done stay done |

4. After each file it shows its progress, e.g. `done with 12/240 files`, and an estimate of the remaining time.

With `--no-interaction` (`-n`) every question is answered with the default, `execute`, so all changes are applied without asking.

### Progress

`rector-p` stores a SHA-256 hash of every file marked as done in `cache.json` inside its package directory (usually `vendor/andersundsehr/rector-p/cache.json`). A file is skipped as long as its content does not change.

- Start from the beginning with `--startOver` (`-s`), for example after you added rules to `rector.php`.
- The progress is lost when Composer reinstalls the package.

### Chunks

Split the sorted file list into equal parts and work on one part at a time, for example to share the work in a team or over several days:

```bash
vendor/bin/rector-p --chunk=1/4   # first quarter
vendor/bin/rector-p --chunk=2/4   # second quarter
```

After a chunk, `rector-p` prints the command for the next one.

## Options

```text
Usage:
  partial [options] [--] [<source>...]

Arguments:
  source                Files or directories to be upgraded.

Options:
  -s, --startOver       Start over with the first file (be default rector-p keeps a record of files that have no changes in them)
  -p, --chunk=CHUNK     chunk(part) definition eg 1/2 (first half) or 2/2 (second half) or 3/10 (third tenth) [default: "1/1"]
  -c, --config=CONFIG   Path to config file [default: "<current directory>/rector.php"]
  -h, --help            Display help for the given command. When no command is given display help for the partial command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

- `--config` only selects the file that the list of files is read from. The Rector runs for the single files use Rector's default, `rector.php` in the current directory.
- With `-vv` `rector-p` prints every Rector command, with `-vvv` also how long it took.
- `rector-p` calls `rector` from Composer's `vendor/bin` directory, or from your `PATH` if it cannot find it there.
- If applying the changes to a file fails, `rector-p` stops with Rector's exit code.

## Contributing

This package is developed in the monorepo [andersundsehr/grumphp-config](https://github.com/andersundsehr/grumphp-config/tree/main/packages/rector-p). Please open issues and pull requests there.

## License

GPL-2.0-or-later

---

Made with ♥️ by [anders und sehr GmbH](https://www.andersundsehr.com). If something does not work or you like this tool, let us know. We are hiring: https://www.andersundsehr.com/karriere/
