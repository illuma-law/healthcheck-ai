<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi\Tests;

use IllumaLaw\HealthCheckAi\HealthCheckAiServiceProvider;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Health\HealthServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            HealthServiceProvider::class,
            AiServiceProvider::class,
            HealthCheckAiServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
