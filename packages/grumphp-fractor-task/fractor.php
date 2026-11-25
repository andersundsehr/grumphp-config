<?php

declare(strict_types=1);

use a9f\Fractor\Configuration\FractorConfiguration;
use PLUS\GrumPHPConfig\FractorSettings;

return FractorSettings::apply(FractorConfiguration::configure())
    // add your own sets, rules and skips, see https://github.com/andreaswolf/fractor
    ->withSkip([
        // fractor should not touch these files
        //__DIR__ . '/Resources/Private/Example',
    ]);
