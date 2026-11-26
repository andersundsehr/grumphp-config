<?php

declare(strict_types=1);

namespace PLUS\GrumPHPConfig;

use a9f\Fractor\Application\Contract\FractorRule;
use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\Fractor\Configuration\FractorConfigurationBuilder;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;
use a9f\FractorTypoScript\Configuration\TypoScriptProcessorOption;
use a9f\Typo3Fractor\TYPO3v10\TypoScript\RemoveUseCacheHashFromTypolinkTypoScriptFractor;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConditionTermination;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConfiguration;

use function array_filter;
use function array_values;
use function explode;
use function shell_exec;
use function trigger_error;

final class FractorSettings
{
    /**
     * Applies the shared configuration to the Fractor configuration of the project:
     *
     * return FractorSettings::apply()
     *     ->withSkip([__DIR__ . '/Resources/Private/Legacy']);
     *
     * withOptions() of the project replaces the options set here.
     *
     * @param FractorConfigurationBuilder|null $fractorConfig the builder to configure, default: FractorConfiguration::configure()
     */
    public static function apply(?FractorConfigurationBuilder $fractorConfig = null): FractorConfigurationBuilder
    {
        return ($fractorConfig ?? FractorConfiguration::configure())
            ->withPaths(self::gitFiles())
            ->withSets(self::internalSets())
            ->withRules(self::internalRules())
            ->withOptions(self::internalOptions());
    }

    /**
     * @return list<string> all files tracked in git
     */
    private static function gitFiles(): array
    {
        return array_values(array_filter(explode("\n", (string)shell_exec('git ls-files | xargs ls -d 2>/dev/null'))));
    }

    /**
     * The TYPO3 set with all upgrades up to the lowest TYPO3 version of the project, for older fractor.php files.
     *
     * @param bool $entirety no longer supported: the set always contains all upgrades up to the version
     * @return array<int, string>
     * @deprecated will be removed in version 13, use FractorSettings::apply()
     */
    public static function sets(bool $entirety = true): array
    {
        if (!$entirety) {
            trigger_error('FractorSettings::sets(false) is no longer supported, the set contains all upgrades up to the version', E_USER_DEPRECATED);
        }

        return self::internalSets();
    }

    /**
     * @return array<int, string>
     */
    private static function internalSets(): array
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
        ];
    }

    /**
     * The TypoScript options for older fractor.php files.
     *
     * @return array<string, bool|PrettyPrinterConditionTermination|int|string>
     * @deprecated will be removed in version 13, use FractorSettings::apply()
     */
    public static function options(): array
    {
        return self::internalOptions();
    }

    /**
     * @return array<string, bool|PrettyPrinterConditionTermination|int|string>
     */
    private static function internalOptions(): array
    {
        $minimalTypo3Version = VersionUtility::getMinimalTypo3Version();
        if (!$minimalTypo3Version) {
            return [];
        }

        return array_filter(
            [
                TypoScriptProcessorOption::INDENT_SIZE => 2,
                TypoScriptProcessorOption::INDENT_CHARACTER => PrettyPrinterConfiguration::INDENTATION_STYLE_SPACES,
                TypoScriptProcessorOption::ADD_CLOSING_GLOBAL => false,
                TypoScriptProcessorOption::INCLUDE_EMPTY_LINE_BREAKS => true,
                TypoScriptProcessorOption::INDENT_CONDITIONS => true,
                TypoScriptProcessorOption::CONDITION_TERMINATION => PrettyPrinterConditionTermination::EnforceEnd,
            ]
        );
    }

    /**
     * The rules for older fractor.php files.
     *
     * @return array<class-string<FractorRule>>
     * @deprecated will be removed in version 13, use FractorSettings::apply()
     */
    public static function rules(): array
    {
        return self::internalRules();
    }

    /**
     * @return array<class-string<FractorRule>>
     */
    private static function internalRules(): array
    {
        $minimalTypo3Version = VersionUtility::getMinimalTypo3Version();
        if (!$minimalTypo3Version) {
            // we need at least one rule to run fractor and we install fractor even if typo3 is not installed
            return [
                RemoveUseCacheHashFromTypolinkTypoScriptFractor::class,
            ];
        }

        return [];
    }
}
