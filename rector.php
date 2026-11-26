<?php

declare(strict_types=1);

use PLUS\GrumPHPConfig\RectorSettings;

return RectorSettings::apply()
    // add your own sets, rules and skips, see https://getrector.com/documentation
    ->withSkip([
        // rector should not touch these files
        //__DIR__ . '/src/Example',
        //__DIR__ . '/src/Example.php',
    ]);
