[![Packagist](https://img.shields.io/packagist/v/pluswerk/grumphp-bom-task.svg?style=flat-square)](https://packagist.org/packages/pluswerk/grumphp-bom-task)
[![Packagist](https://img.shields.io/packagist/l/pluswerk/grumphp-bom-task.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)
[![Travis](https://img.shields.io/travis/Kanti/LJSON.svg?style=flat-square)](https://travis-ci.org/Pluswerk/grumphp-bom-task)
[![Code Climate](https://img.shields.io/codeclimate/maintainability/pluswerk/grumphp-bom-task.svg?style=flat-square)](https://codeclimate.com/github/pluswerk/grumphp-bom-task)
[![SymfonyInsight Grade](https://img.shields.io/symfony/i/grade/69cf4b58-b856-4f79-a3da-a89291eae102.svg?style=flat-square)](https://insight.symfony.com/projects/69cf4b58-b856-4f79-a3da-a89291eae102)

# grumphp-bom-task

Force files to have no BOM via GrumPHP

### grumphp.yml:

````yml
parameters:
    tasks:
        plus_bom_fixer:
            triggered_by:  [php, css, scss, less, json, sql, yml, txt]
    extensions:
        - PLUS\GrumPHPBomTask\ExtensionLoader
````

### Upgrade from andersundsehr/grumphp-bom-task

If you come from [andersundsehr/grumphp-bom-task](https://github.com/andersundsehr/grumphp-bom-task), change the extensions Loader path in the grumphp.yml file. 

from:

````yml
parameters:
    tasks:
        aus_bom_fixer:
            triggered_by:  [php, css, scss, less, json, sql, yml, txt]
    extensions:
        - AUS\GrumPHPBomTask\ExtensionLoader
````

to:

````yml
parameters:
    tasks:
        plus_bom_fixer:
            triggered_by:  [php, css, scss, less, json, sql, yml, txt]
    extensions:
        - PLUS\GrumPHPBomTask\ExtensionLoader
````

### Composer

``composer require --dev pluswerk/grumphp-bom-task``
