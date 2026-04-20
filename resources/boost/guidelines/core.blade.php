# illuma-law/healthcheck-ai

AI health checks for Spatie's Laravel Health package. Monitors LLM prompt providers, embedding chains, and AI Agent registries.

## Key Checks

### 1. AI Prompt Chain Health Check
Monitors a sequence of LLM providers. Marks as degraded (Warning) if primary fails but fallback succeeds.

```php
use IllumaLaw\HealthCheckAi\AiPromptChainHealthCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    AiPromptChainHealthCheck::new()
        ->cacheTtl(600)
        ->timeout(10)
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'gpt-4o'],
            ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-latest'],
        ]),
]);
```

### 2. AI Embedding Chain Health Check
Verifies embedding models generate vectors with expected dimensions.

```php
use IllumaLaw\HealthCheckAi\AiEmbeddingChainHealthCheck;

Health::checks([
    AiEmbeddingChainHealthCheck::new()
        ->dimensions(1536)
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'text-embedding-3-small'],
        ]),
]);
```

### 3. AI Agent Registry Check
Inspects Agent classes for required `Provider` and `Model` attributes and credentials.

```php
use IllumaLaw\HealthCheckAi\AiAgentRegistryCheck;

Health::checks([
    AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => [
            \App\Agents\SupportAgent::class,
        ])
        ->hasCredentialsUsing(fn (string $provider) => !empty(config("ai.providers.{$provider}.api_key"))),
]);
```

## Configuration

Publish config: `php artisan vendor:publish --tag="healthcheck-ai-config"`

Key options in `config/healthcheck-ai.php`:
- `embedding_cache_ttl_seconds`: Default 300
- `embedding_dimensions`: Default 768
- `prompt_cache_ttl_seconds`: Default 300
- `prompt_timeout_seconds`: Default 25
