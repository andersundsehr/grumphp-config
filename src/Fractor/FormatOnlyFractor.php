<?php

declare(strict_types=1);

namespace PLUS\GrumPHPConfig\Fractor;

use a9f\FractorXml\AbstractXmlFractor;
use DOMNode;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Changes nothing itself. Fractor only runs with at least one rule, and only then its file processors
 * format XML, XLIFF, YAML, Fluid and .htaccess files. Projects without TYPO3 have no other rules.
 */
final class FormatOnlyFractor extends AbstractXmlFractor
{
    public function canHandle(DOMNode $node): bool
    {
        return false;
    }

    public function refactor(DOMNode $node): DOMNode|int|null
    {
        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Changes nothing, so that Fractor formats XML, XLIFF, YAML, Fluid and .htaccess files in projects without TYPO3',
            [
                new CodeSample(
                    "<config>\n      <item/>\n</config>",
                    "<config>\n    <item/>\n</config>"
                ),
            ]
        );
    }
}
