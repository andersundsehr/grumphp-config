<?php

declare(strict_types=1);

namespace PLUS\GrumPHPConfig;

use Composer\InstalledVersions;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\CodingStyle\Rector\If_\NullableCompareToNullRector;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\Php70\Rector\Assign\ListSwapArrayOrderRector;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Strict\Rector\BooleanNot\BooleanInBooleanNotRuleFixerRector;
use Rector\Strict\Rector\If_\BooleanInIfConditionRuleFixerRector;
use Rector\Strict\Rector\Ternary\BooleanInTernaryOperatorRuleFixerRector;
use Rector\Strict\Rector\Ternary\DisallowedShortTernaryRuleFixerRector;
use Rector\TypeDeclaration\Rector\BooleanAnd\BinaryOpNullableToInstanceofRector;
use Ssch\TYPO3Rector\CodeQuality\General\RenameClassMapAliasRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;

final class RectorSettings
{
    /**
     * @return array<int,string>
     */
    public static function sets(bool $entirety = false): array
    {
        $phpVersion = VersionUtility::getMinimalPhpVersion() ?? PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        [$major, $minor] = explode('.', $phpVersion, 3);
        $phpFile = constant(SetList::class . '::PHP_' . $major . $minor);
        if ($entirety) {
            $phpFile = constant(LevelSetList::class . '::UP_TO_PHP_' . $major . $minor);
        }

        assert(is_string($phpFile));

        return array_filter(
            [
                SetList::CODE_QUALITY, // YES
                SetList::CODING_STYLE, // YES
                SetList::DEAD_CODE, // YES
                SetList::STRICT_BOOLEANS, // only DisallowedEmptyRuleFixerRector
                //SetList::GMAGICK_TO_IMAGICK, // NO
                //SetList::NAMING, //NO is not good
                SetList::PRIVATIZATION, // some things may be bad
                SetList::TYPE_DECLARATION, // YES
                SetList::EARLY_RETURN, // YES
                SetList::INSTANCEOF,
                $phpFile,
                //SetList::PHP_52, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_53, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_54, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_55, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_56, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_70, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_71, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_72, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_73, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_74, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_80, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_81, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
                //SetList::PHP_82, // YES, included in LevelSetList::class . '::UP_TO_PHP_' ...
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    public static function setsTypo3(bool $entirety = false): array
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
            case 'dev-main':
                $setList = $entirety ? Typo3LevelSetList::UP_TO_TYPO3_13 : Typo3SetList::TYPO3_13;
                break;
        }

        assert(is_string($setList));
        return [
            $setList,
            __DIR__ . '/../rector-typo3-rule-set.php',
           Typo3SetList::CODE_QUALITY,
           Typo3SetList::GENERAL,
        ];
    }

    /**
     * @return array<string|string[]>
     */
    public static function skip(): array
    {
        return array_filter([
            /**
             * FROM: if($object) {
             * TO:   if($object !== null) {
             */
            NullableCompareToNullRector::class,
            /**
             * FROM: if ($dateTime === null) {
             * TO:   if (! $dateTime instanceof DateTime) {
             */
            FlipTypeControlToUseExclusiveTypeRector::class,
            /**
             * FROM: if ($someClass && $someClass->someMethod()) {
             * TO:   if ($someClass instanceof SomeClass && $someClass->someMethod()) {
             */
            BinaryOpNullableToInstanceofRector::class,
            /**
             * FROM: $i++
             * TO:   ++$i
             */
            PostIncDecToPreIncDecRector::class,
            /**
             * FROM: if(count($array)) {
             * TO:   if($array !== []) {
             */
            CountArrayToEmptyArrayComparisonRector::class,
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
             * DOCS: be careful, run this just once, since it can keep swapping order back and forth
             * => we don't do it once!
             */
            ListSwapArrayOrderRector::class,
            /**
             * FROM: 1305630314
             * TO:   1_305_630_314
             */
            AddLiteralSeparatorToNumberRector::class,
            /**
             * Maybe to a later date?
             *
             * FROM: if($x) {
             * TO:   $x ? $abcde + $xyz : $trsthjzuj - $gesrtdnzmf
             */
            SimplifyIfElseToTernaryRector::class,
            /**
             * FROM: if ($timeInMinutes % 60) {
             * TO:   if ($timeInMinutes % 60 !== 0) {
             */
            ExplicitBoolCompareRector::class,
            /**
             * FROM: (!self::$email) {
             * TO:   if (self::$email === '' || self::$email === '0') {
             */
            BooleanInBooleanNotRuleFixerRector::class,
            BooleanInIfConditionRuleFixerRector::class,
            /**
             * FROM: $filter['userGroup'] = max($userGroups ?: [0]);
             * TO:   $filter['userGroup'] = max($userGroups !== [] ? $userGroups : [0]);
             */
            DisallowedShortTernaryRuleFixerRector::class,
            /**
             * FROM: isset($this->x);
             * TO:   property_exists($this, 'x') && $this->x !== null;
             */
            IssetOnPropertyObjectToPropertyExistsRector::class,
            /**
             * FROM: $ext ? $ext : '';
             * TO:   $ext !== '' && $ext !== '0' && $ext !== [] ? $ext : '';
             */
            BooleanInTernaryOperatorRuleFixerRector::class,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function skipTypo3(): array
    {
        if (!InstalledVersions::isInstalled('typo3/cms-core')) {
            return [];
        }

        return [
            /**
             * not used:
             */
            RenameClassMapAliasRector::class,
            /**
             * in combination with ConstantsToEnvironmentApiCallRector not the best rule
             */
            SensitiveConstantNameRector::class,
        ];
    }
}
