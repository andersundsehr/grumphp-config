[![Packagist](https://img.shields.io/packagist/v/pluswerk/grumphp-config.svg?style=flat-square)](https://packagist.org/packages/pluswerk/grumphp-config)
[![Packagist](https://img.shields.io/packagist/l/pluswerk/grumphp-config.svg?style=flat-square)](https://opensource.org/licenses/LGPL-3.0)
[![Code Climate](https://img.shields.io/codeclimate/maintainability/pluswerk/grumphp-xliff-task.svg?style=flat-square)](https://codeclimate.com/github/pluswerk/grumphp-xliff-task)

# Install

````bash
composer require pluswerk/grumphp-config --dev
````

pluswerk/grumphp-config will create `grumphp.yml`, `rector.php`, `phpstan.neon`, `phpstan-baseline.neon` and require some project specific resources if necessary 

### You want to override settings?:

Look into your generated grumphp.yml

## Development

DDEV and Docker Compose use PHP 8.5 by default. With DDEV, run `ddev start`
and `ddev composer install --no-scripts`.

To use Docker Compose, run `docker compose run --rm web composer install --no-scripts`.
Select an older supported PHP version with `PHP_VERSION`, for example:

```bash
PHP_VERSION=8.2 docker compose run --rm web php -v
```

PHP 8.2, 8.3, 8.4 and 8.5 remain supported. CI checks each version without
ignoring PHP platform requirements, both without TYPO3 and with TYPO3 13 and 14.
