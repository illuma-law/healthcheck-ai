<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('it will use strict types')
    ->expect('IllumaLaw\HealthCheckAi')
    ->toUseStrictTypes();
