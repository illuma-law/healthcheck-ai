<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi\Tests\Mocks;

use Laravel\Ai\Attributes\Provider;

#[Provider('anthropic')]
class MissingModelAgent {}
