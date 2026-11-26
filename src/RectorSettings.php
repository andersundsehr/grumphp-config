<?php

declare(strict_types=1);

namespace PLUS\GrumPHPConfig;

use Composer\InstalledVersions;
use Rector\CodeQuality\Rector\If_\ArrayExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\Configuration\RectorConfigBuilder;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;
use stdClass;

use function array_filter;
use function array_values;
use function assert;
use function explode;
use function is_string;
use function shell_exec;
use function trigger_error;

final class RectorSettings
{
    /**
     * Applies the shared configuration to the Rector configuration of the project:
     *
     * return RectorSettings::apply()
     *     ->withSkip([__DIR__ . '/src/Legacy']);
     *
     * @param RectorConfigBuilder|null $rectorConfig the builder to configure, default: RectorConfig::configure()
     * @param bool $phpSets false: no PHP upgrade sets, e.g. to call withPhpSets() with your own version
     * @param bool $typo3 false: no TYPO3 sets, even if typo3/cms-core is installed
     */
    public static function apply(?RectorConfigBuilder $rectorConfig = null, bool $phpSets = true, bool $typo3 = true): RectorConfigBuilder
    {
        $rectorConfig ??= RectorConfig::configure();
        $rectorConfig
            ->withParallel()
            ->withImportNames()
            ->withCache('./var/cache/rector')
            ->withPaths(self::gitPhpFiles())
            ->withPreparedSets(
                deadCode: true,
                codeQuality: true,
                codingStyle: true,
                typeDeclarations: true,
                privatization: true,
            )
            ->withSkip(self::internalSkip());

        if ($phpSets) {
            if (VersionUtility::getMinimalPhpVersion() !== null) {
                // the PHP version from require.php in composer.json
                $rectorConfig->withPhpSets();
            } else {
                // without a PHP requirement: the PHP version that runs Rector
                $rectorConfig = match (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) {
                    '8.2' => $rectorConfig->withPhpSets(php82: true),
                    '8.3' => $rectorConfig->withPhpSets(php83: true),
                    '8.4' => $rectorConfig->withPhpSets(php84: true),
                    '8.5' => $rectorConfig->withPhpSets(php85: true),
                    default => $rectorConfig->withPhpSets(php86: true),
                };
            }
        }

        if ($typo3) {
            $rectorConfig->withSets(self::internalSetsTypo3());
        }

        return $rectorConfig;
    }

    /**
     * @return list<string> all PHP files tracked in git
     */
    private static function gitPhpFiles(): array
    {
        return array_values(array_filter(explode("\n", (string)shell_exec("git ls-files | xargs ls -d 2>/dev/null | grep -E '\\.(php)$'"))));
    }

    /**
     * The sets for older rector.php files with $rectorConfig->sets().
     *
     * The PHP rules come from SetList::PHP_VERSION_BASED_SET, Rector applies the ones up to the PHP version
     * from require.php in composer.json, or the PHP version that runs Rector.
     *
     * @param bool $entirety no longer supported: the sets always contain all upgrades up to the versions
     * @return array<int,string>
     * @deprecated will be removed in version 13, use RectorSettings::apply()
     */
    public static function sets(bool $entirety = true): array
    {
        if (!$entirety) {
            trigger_error('RectorSettings::sets(false) is no longer supported, the sets contain all upgrades up to the versions', E_USER_DEPRECATED);
        }

        return array_filter(
            [
                SetList::CODE_QUALITY, // YES
                SetList::CODING_STYLE, // YES
                SetList::DEAD_CODE, // YES
                //SetList::GMAGICK_TO_IMAGICK, // NO
                //SetList::NAMING, //NO is not good
                SetList::PRIVATIZATION, // some things may be bad
                SetList::TYPE_DECLARATION, // YES
                SetList::PHP_VERSION_BASED_SET,
                ...self::internalSetsTypo3(),
            ],
        );
    }

    /**
     * @return array<int, string>
     * @deprecated can be removed
     */
    public static function setsTypo3(bool $entirety = false): array
    {
        // nonsense method to not break BC
        if ($entirety) {
            return (array)new stdClass();
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private static function internalSetsTypo3(): array
    {
        $setList = null;
        $minimalTypo3Version = VersionUtility::getMinimalTypo3Version();
        if (!$minimalTypo3Version) {
            return [];
        }

        [$major] = explode('.', $minimalTypo3Version, 2);

        switch ($major) {
            case 10:
                $setList = Typo3LevelSetList::UP_TO_TYPO3_10;
                break;
            case 11:
                $setList = Typo3LevelSetList::UP_TO_TYPO3_11;
                break;
            case 12:
                $setList = Typo3LevelSetList::UP_TO_TYPO3_12;
                break;
            case 13:
                $setList = Typo3LevelSetList::UP_TO_TYPO3_13;
                break;
            case 14:
            case 'dev-main':
                $setList = Typo3LevelSetList::UP_TO_TYPO3_14;
                break;
        }

        assert(is_string($setList));
        return [
            $setList,
            Typo3SetList::CODE_QUALITY,
            Typo3SetList::GENERAL,
        ];
    }

    /**
     * The skips for older rector.php files with $rectorConfig->skip().
     *
     * @return array<int|string, list<string>|string>
     * @deprecated will be removed in version 13, use RectorSettings::apply()
     */
    public static function skip(): array
    {
        return self::internalSkip();
    }

    /**
     * @return array<int|string, list<string>|string>
     */
    private static function internalSkip(): array
    {
        return [
            /**
             * Keep nullable object checks unchanged:
             * FROM: if ($dateTime === null) {, if ($object) {, if ($someClass && $someClass->someMethod()) {
             * TO:   if (! $dateTime instanceof DateTime) {, if ($object instanceof SomeClass) {, ...
             */
            ExplicitNullableObjectInstanceRector::class,
            /**
             * FROM: protected string $name;
             * TO:   private string  $name;
             *
             * ignore for models so the protected attributes are not made private
             */
            PrivatizeFinalClassPropertyRector::class => [
                '/*/Model/*',
            ],
            /**
             * FROM: if ($array) {
             * TO:   if ($array !== []) {
             */
            ArrayExplicitBoolCompareRector::class,
            /**
             * FROM: isset($this->x);
             * TO:   property_exists($this, 'x') && $this->x !== null;
             */
            IssetOnPropertyObjectToPropertyExistsRector::class,

            ...self::internalSkipTypo3(),
        ];
    }

    /**
     * @return array<int|string, list<string>|string>
     * @deprecated can be removed
     */
    public static function skipTypo3(): array
    {
        return [];
    }

    /**
     * @return array<int|string, list<string>|string>
     */
    private static function internalSkipTypo3(): array
    {
        if (!InstalledVersions::isInstalled('typo3/cms-core')) {
            return [];
        }

        return [
            /**
             * in combination with ConstantsToEnvironmentApiCallRector not the best rule
             */
            SensitiveConstantNameRector::class,

            // @see https://github.com/sabbelasichon/typo3-rector/issues/2536
            getcwd() . '/**/Configuration/ExtensionBuilder/*',
            NameImportingPostRector::class => [
                'ClassAliasMap.php',
            ],
            SafeDeclareStrictTypesRector::class => [
                getcwd() . '/ext_emconf.php',
            ],
        ];
    }
}
