<?php

declare(strict_types=1);

use PLUS\GrumPHPConfig\FractorSettings;

return FractorSettings::apply()
    // add your own sets, rules and skips, see https://github.com/andreaswolf/fractor
    ->withSkip([
        // fractor should not touch these files
        //__DIR__ . '/Resources/Private/Example',
    ]);
