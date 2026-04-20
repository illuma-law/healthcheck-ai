# Healthcheck AI for Laravel

[![Tests](https://github.com/illuma-law/healthcheck-ai/actions/workflows/run-tests.yml/badge.svg)](https://github.com/illuma-law/healthcheck-ai/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/illuma-law/healthcheck-ai?label=Version)](https://packagist.org/packages/illuma-law/healthcheck-ai)

A comprehensive suite of AI health checks for Spatie's [Laravel Health](https://spatie.be/docs/laravel-health/v1/introduction) package.

This package provides robust health checks specifically designed to monitor your AI integrations, ensuring that your LLM prompt providers, embedding chains, and AI Agent registries are operational and properly configured.

## Features

- **Prompt Chain Check:** Validates your LLM failover chains (e.g., OpenAI → Anthropic) by sending a small canary prompt.
- **Embedding Chain Check:** Verifies that your embedding providers are generating vectors with the expected dimensions.
- **Agent Registry Check:** Inspects your AI agents to ensure that `Model` and `Provider` attributes are present and that the corresponding API credentials exist in your environment.
- **Caching:** Expensive API calls to AI providers are cached to prevent your health check endpoint from exhausting your rate limits.

## Installation

Require this package with composer:

```shell
composer require illuma-law/healthcheck-ai
```

## Configuration

You can publish the configuration file to customize the default cache TTLs, embedding dimensions, and canary texts used during the checks:

```shell
php artisan vendor:publish --tag="healthcheck-ai-config"
```

This will create a `config/healthcheck-ai.php` file:

```php
return [
    // How long to cache embedding check results to save API calls
    'embedding_cache_ttl_seconds' => 300,
    
    // Default expected dimensions for vector embeddings
    'embedding_dimensions' => 768,
    
    // The short text sent to the embedding provider
    'embedding_canary_text' => 'AI embedding health check',
    
    // How long to cache the LLM prompt check results
    'prompt_cache_ttl_seconds' => 300,
    
    // The prompt sent to the LLM to verify it is responsive
    'prompt_text' => 'Reply with exactly: OK',
    
    // Request timeout in seconds
    'prompt_timeout_seconds' => 25,
];
```

## Usage & Integration

Add the checks to your `Health::checks()` registration within a Service Provider (usually `AppServiceProvider`).

### 1. Prompt Chain Check

This check monitors a sequence of LLM providers. If the primary provider fails but a fallback succeeds, it marks the health check as degraded (Warning) instead of failing completely.

```php
use IllumaLaw\HealthCheckAi\AiPromptChainHealthCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    AiPromptChainHealthCheck::new()
        ->cacheTtl(600) // Optional override
        ->timeout(10) // Optional override
        ->resolveChainUsing(function () {
            // Define your fallback chain
            return [
                ['provider' => 'openai', 'model' => 'gpt-4o'],
                ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-latest'],
            ];
        }),
]);
```

### 2. Embedding Chain Check

This check is vital if you rely on vector embeddings (e.g., pgvector or Typesense). It queries your embedding models and verifies that the output vectors match your expected dimensions.

```php
use IllumaLaw\HealthCheckAi\AiEmbeddingChainHealthCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    AiEmbeddingChainHealthCheck::new()
        ->dimensions(1536) // e.g., OpenAI text-embedding-3-small
        ->resolveChainUsing(function () {
            return [
                ['provider' => 'openai', 'model' => 'text-embedding-3-small'],
            ];
        }),
]);
```

### 3. Agent Registry Check

If you are using the `laravel/ai` SDK with attributes for Agents, this check inspects a given list of Agent classes using PHP Reflection. It verifies that each class defines its `Provider` and `Model` attributes and that your application has the credentials required for that provider.

```php
use IllumaLaw\HealthCheckAi\AiAgentRegistryCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(function () {
            // Return a list of your Agent class names
            return [
                 \App\Agents\SupportAgent::class,
                 \App\Agents\SummarizerAgent::class,
            ];
        })
        ->hasCredentialsUsing(function (string $provider) {
            // Define how to check if credentials exist for a given provider
            if ($provider === 'openai') {
                return !empty(config('ai.providers.openai.api_key'));
            }
            if ($provider === 'anthropic') {
                return !empty(config('ai.providers.anthropic.api_key'));
            }
            return false;
        }),
]);
```

## Testing

Run the test suite:

```shell
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
