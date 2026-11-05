[![Packagist](https://img.shields.io/packagist/v/andersundsehr/phpstan-git-files.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/phpstan-git-files)
[![Packagist](https://img.shields.io/packagist/l/andersundsehr/phpstan-git-files.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)

# andersundsehr/phpstan-git-files

A [PHPStan](https://phpstan.org/) extension that analyses all PHP files tracked in git.

You no longer maintain `paths` in your `phpstan.neon`: new files are analysed as soon as they are added to git, and untracked or ignored files, such as `vendor/` or generated code, stay out.

> This extension is part of [`pluswerk/grumphp-config`](https://github.com/andersundsehr/grumphp-config). If you use that package, the generated `phpstan.neon` already includes it.

## Requirements

- PHP 8.2 or newer
- PHPStan 1.9 or newer, or PHPStan 2
- git and a shell with `xargs`, `ls`, `grep` and `awk`
- `shell_exec()` must be allowed in PHP CLI
- at least one tracked `.php` file

## Installation

```bash
composer require --dev andersundsehr/phpstan-git-files
```

Include the extension in your `phpstan.neon` and remove your own `paths`:

```neon
includes:
  - vendor/andersundsehr/phpstan-git-files/extension.php

parameters:
  level: 8
```

Run PHPStan from the directory that contains your `phpstan.neon`, usually the project root:

```bash
vendor/bin/phpstan analyse
```

## How it works

`extension.php` returns PHPStan configuration with these parameters:

- **`paths`**: all files from `git ls-files` below the current directory that end in `.php` and still exist, as absolute paths.
- **`excludePaths.analyse`**: git-ignored files in one directory, see below.

To keep PHPStan's result cache working (see [issue #3](https://github.com/andersundsehr/phpstan-git-files/issues/3)), `paths` must contain a directory and not only single files. The extension therefore takes the deepest directory that contains a tracked PHP file and lists that directory instead of its files. To keep ignored files in that directory out of the analysis, it adds them to `excludePaths.analyse`. As a side effect, untracked PHP files that are not ignored by git are analysed if they are in that one directory.

The extension runs `git` only once per PHP process and reuses the result.

## Contributing

This package is developed in the monorepo [andersundsehr/grumphp-config](https://github.com/andersundsehr/grumphp-config/tree/main/packages/phpstan-git-files). Please open issues and pull requests there.

## License

LGPL-3.0-or-later

# with ♥️ from anders und sehr GmbH

> If something did not work 😮  
> or you appreciate this Extension 🥰 let us know.

> We are hiring https://www.andersundsehr.com/karriere/
