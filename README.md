# Health Check AI

[![Tests](https://github.com/illuma-law/healthcheck-ai/actions/workflows/run-tests.yml/badge.svg)](https://github.com/illuma-law/healthcheck-ai/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/illuma-law/healthcheck-ai?label=Version)](https://packagist.org/packages/illuma-law/healthcheck-ai)

**Focused AI failover chain and agent registry health checks for Spatie's Laravel Health package**

This package provides a suite of health checks for monitoring AI providers and agent registries in Laravel applications.

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Prompt Chain Check](#prompt-chain-check)
  - [Embedding Chain Check](#embedding-chain-check)
  - [Agent Registry Check](#agent-registry-check)
- [Testing](#testing)
- [Changelog](#changelog)
- [Credits](#credits)
- [License](#license)

## Installation

You can install the package via composer:

```bash
composer require illuma-law/healthcheck-ai
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="healthcheck-ai-config"
```

## Configuration

The configuration file allows you to define default cache TTLs and canary values:

```php
return [
    'embedding_cache_ttl_seconds' => 300,
    'embedding_dimensions' => 768,
    'embedding_canary_text' => 'legal embedding health check',
    'prompt_cache_ttl_seconds' => 300,
    'prompt_text' => 'Reply with exactly: OK',
    'prompt_timeout_seconds' => 25,
];
```

## Usage

### Prompt Chain Check

Monitors a sequence of LLM providers. If the primary fails but a fallback succeeds, it returns a warning.

```php
use IllumaLaw\HealthCheckAi\AiPromptChainHealthCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    AiPromptChainHealthCheck::new()
        ->resolveChainUsing(fn() => [
            ['provider' => 'openai', 'model' => 'gpt-4o'],
            ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-latest'],
        ]),
]);
```

### Embedding Chain Check

Monitors embedding providers and validates output dimensions.

```php
use IllumaLaw\HealthCheckAi\AiEmbeddingChainHealthCheck;

Health::checks([
    AiEmbeddingChainHealthCheck::new()
        ->dimensions(1536)
        ->resolveChainUsing(fn() => [
            ['provider' => 'openai', 'model' => 'text-embedding-3-small'],
        ]),
]);
```

### Agent Registry Check

Ensures all registered AI agents have the necessary credentials and model definitions.

```php
use IllumaLaw\HealthCheckAi\AiAgentRegistryCheck;

Health::checks([
    AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn() => [
             \App\Agents\LegalResearcher::class,
             \App\Agents\ContractAnalyzer::class,
        ])
        ->hasCredentialsUsing(fn($provider) => !empty(config("ai.providers.{$provider}.api_key"))),
]);
```

## Testing

The package includes a comprehensive test suite using Pest, with 100% code coverage.

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [illuma-law](https://github.com/illuma-law)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
