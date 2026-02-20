[![Packagist](https://img.shields.io/packagist/v/andersundsehr/grumphp-fractor-task.svg?style=flat-square)](https://packagist.org/packages/andersundsehr/grumphp-fractor-task)
[![Packagist](https://img.shields.io/packagist/l/andersundsehr/grumphp-fractor-task.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)

# grumphp-fractor-task

Add tasks for fractor https://github.com/andreaswolf/fractor-typo3-fractor

## Composer
```bash
composer require --dev andersundsehr/grumphp-fractor-task
```

## Config
```yaml
# grumphp.yml
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

**config**

*Default: null*

With this parameter you can specify the path your project's configuration file. When 'null' fractor will run with the default file: fractor.php

**triggered_by**

*Default: [php]*

This is a list of extensions to be sniffed.


**ignore_patterns**

*Default: []*

This is a list of patterns that will be ignored by Fractor. With this option you can skip files like
tests. Leave this option blank to run Fractor for every php file/directory specified in your
configuration.


**clear_cache**

*Default: true*

With this parameter you can run Fractor without using the cache.

**no_diffs**

*Default: false*

With this parameter you can run Fractor without showing file diffs.
