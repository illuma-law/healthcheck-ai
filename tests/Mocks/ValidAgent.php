<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi\Tests\Mocks;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;

#[Provider('openai')]
#[Model('gpt-4')]
class ValidAgent {}
