[![Packagist](https://img.shields.io/packagist/v/pluswerk/grumphp-xliff-task.svg?style=flat-square)](https://packagist.org/packages/pluswerk/grumphp-xliff-task)
[![Packagist](https://img.shields.io/packagist/l/pluswerk/grumphp-xliff-task.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)
[![Travis](https://img.shields.io/travis/Kanti/LJSON.svg?style=flat-square)](https://travis-ci.org/Pluswerk/grumphp-xliff-task)
[![Code Climate](https://img.shields.io/codeclimate/maintainability/pluswerk/grumphp-xliff-task.svg?style=flat-square)](https://codeclimate.com/github/pluswerk/grumphp-xliff-task)
[![SymfonyInsight Grade](https://img.shields.io/symfony/i/grade/0dffac96-6dda-48b1-b0a4-452bddaffc50.svg?style=flat-square)](https://insight.symfony.com/projects/0dffac96-6dda-48b1-b0a4-452bddaffc50)

# grumphp-xliff-task

GrumPHP task to lint xlf/xliff Files

### grumphp.yml:

```yml
parameters:
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

### upgrade from andersundsehr/gurmphp-xliff-task

If you come from [andersundsehr/grumphp-xliff-task](https://github.com/andersundsehr/grumphp-xliff-task), change the extensions Loader path in the grumphp.yml file. 

from:

```yml
        - AUS\GrumPHPXliffTask\ExtensionLoader
```

to:

```yml
        - PLUS\GrumPHPXliffTask\ExtensionLoader
```

### Composer

``composer require --dev pluswerk/grumphp-xliff-task``
