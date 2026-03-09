<?php

declare(strict_types=1);

namespace PLUS\GrumPHPConfig;

use a9f\Fractor\Application\Contract\FractorRule;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;
use a9f\Typo3Fractor\Set\Typo3SetList;
use a9f\FractorTypoScript\Configuration\TypoScriptProcessorOption;
use a9f\Typo3Fractor\TYPO3v10\TypoScript\RemoveUseCacheHashFromTypolinkTypoScriptFractor;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConditionTermination;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConfiguration;

use function array_filter;

final class FractorSettings
{
    /**
     * @return array<int, string>
     */
    public static function sets(bool $entirety = false): array
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
                $setList = $entirety ? Typo3LevelSetList::UP_TO_TYPO3_11 : Typo3SetList::TYPO3_11;
                break;
            case 12:
                $setList = $entirety ? Typo3LevelSetList::UP_TO_TYPO3_12 : Typo3SetList::TYPO3_12;
                break;
            case 13:
                $setList = $entirety ? Typo3LevelSetList::UP_TO_TYPO3_13 : Typo3SetList::TYPO3_13;
                break;
            case 14:
            case 'dev-main':
                $setList = $entirety ? Typo3LevelSetList::UP_TO_TYPO3_14 : Typo3SetList::TYPO3_14;
                break;
        }

        assert(is_string($setList));
        return [
            $setList,
        ];
    }

    /**
     * @return array<string, bool|PrettyPrinterConditionTermination|int|string>
     */
    public static function options(): array
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
     * @return array<class-string<FractorRule>>
     */
    public static function rules(): array
    {
        $minimalTypo3Version = VersionUtility::getMinimalTypo3Version();
        if (!$minimalTypo3Version) {
            return [
                RemoveUseCacheHashFromTypolinkTypoScriptFractor::class,
            ];
        }

        // we need at least one rule to run fractor and we install fractor even if typo3 is not installed
        return [];
    }
}
