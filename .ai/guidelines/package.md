---
description: AI health checks for Spatie Laravel Health — LLM prompt chains, embedding chains, agent registry
---

# healthcheck-ai

AI integration health checks for `spatie/laravel-health`. Monitors LLM failover chains, embedding providers, and agent registry completeness.

## Namespace

`IllumaLaw\HealthCheckAi`

## Key Checks

- `AiPromptChainHealthCheck` — sends canary prompt through the LLM chain; warns if primary fails but fallback succeeds
- `AiEmbeddingChainHealthCheck` — verifies embedding provider returns vectors of expected dimensions
- `AiAgentRegistryCheck` — ensures all registered agents have `#[Model]`/`#[Provider]` attributes and valid API credentials

## Registration

```php
use IllumaLaw\HealthCheckAi\AiAgentRegistryCheck;
use IllumaLaw\HealthCheckAi\AiEmbeddingChainHealthCheck;
use IllumaLaw\HealthCheckAi\AiPromptChainHealthCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    AiPromptChainHealthCheck::new()
        ->cacheTtl(600)
        ->timeout(10)
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'gpt-4o'],
            ['provider' => 'anthropic', 'model' => 'claude-3-5-haiku'],
        ]),

    AiEmbeddingChainHealthCheck::new()
        ->cacheTtl(300)
        ->expectedDimensions(1536)
        ->resolveChainUsing(fn () => [['provider' => 'openai', 'model' => 'text-embedding-3-small']]),

    AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => PromptRegistry::definitionsByKey()),
]);
```

## Config

Publish: `php artisan vendor:publish --tag="healthcheck-ai-config"`

Configures default cache TTLs, expected embedding dimensions, and canary texts.

## Notes

- Results are cached to avoid rate-limiting AI provider APIs on every health check poll.
- `AiAgentRegistryCheck` warns (not fails) when credentials are missing, so missing optional providers don't break the full suite.
