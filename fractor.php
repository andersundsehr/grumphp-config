<?php

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\FractorTypoScript\Configuration\TypoScriptProcessorOption;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConditionTermination;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConfiguration;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;

/*
$filename = '.editorconfig';

// Check if file exists
if (!file_exists($filename)) {
    die("⚠️ File '$filename' not found.");
}

// Read the file into a string
$content = file_get_contents($filename);

if ($content === false) {
    die("❌ No .editorconfig found -> apply default configuration");
}

echo "📄 Contents of $filename:\n\n";
echo $content;

$indentSize = 2; // Default indent size
$indentCharacter = PrettyPrinterConfiguration::INDENTATION_STYLE_SPACES; // Default indent character

$positionTyposcript = strpos($content, '[*.typoscript]');

if (!$positionTyposcript) {
    $positionAll = strpos($content, '[*]');

    if (!$positionAll) {
        die("❌ no configuration in editorconfig found -> apply default configuration");
    }

    //find the next line after [*] starting with indent_size
    $positionIndentSize = strpos($content, 'indent_size', $positionAll);
    if ($positionIndentSize === false) {
        die("❌ no indent_size found in editorconfig -> apply default configuration");
    }
}
*/

/* @todo: customizable path and settings?  read .editorconfig for possible settings for .typoscript, .xml files etc */
return FractorConfiguration::configure()
    ->withPaths([__DIR__ . '/src/extensions/'])
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_12,
    ])
    ->withOptions([
        TypoScriptProcessorOption::INDENT_SIZE => 2,
        TypoScriptProcessorOption::INDENT_CHARACTER => PrettyPrinterConfiguration::INDENTATION_STYLE_SPACES,
        TypoScriptProcessorOption::ADD_CLOSING_GLOBAL => false,
        TypoScriptProcessorOption::INCLUDE_EMPTY_LINE_BREAKS => true,
        TypoScriptProcessorOption::INDENT_CONDITIONS => true,
        TypoScriptProcessorOption::CONDITION_TERMINATION => PrettyPrinterConditionTermination::Keep,
    ]);
