[![Packagist](https://img.shields.io/packagist/v/pluswerk/grumphp-xliff-task.svg?style=flat-square)](https://packagist.org/packages/pluswerk/grumphp-xliff-task)
[![Packagist](https://img.shields.io/packagist/l/pluswerk/grumphp-xliff-task.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)

# pluswerk/grumphp-xliff-task

A [GrumPHP](https://github.com/phpro/grumphp) task that lints XLIFF translation files (`.xlf`), as TYPO3 uses them. Besides the XML syntax it checks the structure that TYPO3 expects in source and translation files.

> This task is part of [`pluswerk/grumphp-config`](https://github.com/andersundsehr/grumphp-config). If you use that package, the task is already installed and configured.

## Requirements

- PHP 8.2 or newer with the `dom` extension
- GrumPHP 2

## Installation

```bash
composer require --dev pluswerk/grumphp-xliff-task
```

Register the extension and the task in your `grumphp.yml`:

```yaml
grumphp:
  tasks:
    xlifflint:
      ignore_patterns: []
      load_from_net: false
      x_include: false
      dtd_validation: false
      scheme_validation: false
      triggered_by: [xlf]
  extensions:
    - PLUS\GrumPHPXliffTask\ExtensionLoader
```

## Options

The options match those of GrumPHP's [`xmllint`](https://github.com/phpro/grumphp/blob/v2.x/doc/tasks/xmllint.md) task.

| Option | Default | Meaning |
| --- | --- | --- |
| `ignore_patterns` | `[]` | Paths to skip: plain strings that a path must contain, or regular expressions delimited with `/` |
| `load_from_net` | `false` | Allow loading DTD and XSD files from the internet |
| `x_include` | `false` | Resolve XInclude references before checking |
| `dtd_validation` | `false` | Validate against the DTD that the file declares |
| `scheme_validation` | `false` | Validate against the XSD schema that the file declares |
| `triggered_by` | `[xlf]` | File extensions to check |

## What is checked

1. The file is well-formed XML (and valid against its DTD or schema, if enabled).
2. No `<file>` element uses the deprecated `target` attribute. Use `target-language` instead.
3. In a **source file**, where `<file>` has no `target-language`, every `<trans-unit>` has an `id` attribute and a `<source>` element, and no `<target>` element.
4. In a **translation file**, where `<file>` has a `target-language`, every `<trans-unit>` has an `id` attribute and a `<target>` element.

Every finding fails the task, including a `<target>` in a source file, which is reported as a warning.

Source file, e.g. `locallang.xlf`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.0">
    <file source-language="en" datatype="plaintext" original="messages" product-name="extension">
        <header/>
        <body>
            <trans-unit id="button.save">
                <source>Save</source>
            </trans-unit>
        </body>
    </file>
</xliff>
```

Translation file, e.g. `de.locallang.xlf`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.0">
    <file source-language="en" target-language="de" datatype="plaintext" original="messages" product-name="extension">
        <header/>
        <body>
            <trans-unit id="button.save">
                <target>Speichern</target>
            </trans-unit>
        </body>
    </file>
</xliff>
```

## Upgrading from the `AUS` version

Older versions were published as `andersundsehr/grumphp-xliff-task` with the namespace `AUS`. Change the extension in your `grumphp.yml`:

```yaml
grumphp:
  extensions:
    # before: AUS\GrumPHPXliffTask\ExtensionLoader
    - PLUS\GrumPHPXliffTask\ExtensionLoader
```

If your `grumphp.yml` still lists tasks and extensions under `parameters:`, move them under `grumphp:`; GrumPHP 1.0 and newer only read them there.

## Contributing

This package is developed in the monorepo [andersundsehr/grumphp-config](https://github.com/andersundsehr/grumphp-config/tree/main/packages/grumphp-xliff-task). Please open issues and pull requests there.

## License

[LGPL-3.0-or-later](LICENSE.txt)
