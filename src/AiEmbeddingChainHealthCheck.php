<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi;

use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Embeddings;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

final class AiEmbeddingChainHealthCheck extends Check
{
    private const string CACHE_KEY = 'health:ai:embedding_chain:v1';

    /** @var Closure|null */
    private $resolveChainUsing = null;

    private ?int $dimensions = null;

    private ?int $cacheTtl = null;

    public function resolveChainUsing(Closure $callback): self
    {
        $this->resolveChainUsing = $callback;

        return $this;
    }

    public function dimensions(int $dimensions): self
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    public function cacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;

        return $this;
    }

    public function run(): Result
    {
        if (! $this->resolveChainUsing) {
            return Result::make()->failed('Missing chain resolver for AiEmbeddingChainHealthCheck');
        }

        $configTtl = config('healthcheck-ai.embedding_cache_ttl_seconds');
        $ttl = $this->cacheTtl ?? (is_int($configTtl) ? $configTtl : 300);

        /** @var array{results: list<array<string, mixed>>, primary_ok: bool, dimensions: int} $payload */
        $payload = Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->probe());

        $meta = [
            'cached' => true,
            'cache_ttl_seconds' => $ttl,
            'dimensions' => $payload['dimensions'],
            'steps' => $payload['results'],
        ];

        $result = Result::make()->meta($meta)->shortSummary($payload['primary_ok'] ? 'Primary OK' : 'Primary degraded');

        if ($payload['results'] === []) {
            $noProvidersMsg = __('healthcheck-ai::messages.embedding_chain.no_providers');

            return $result->failed(is_string($noProvidersMsg) ? $noProvidersMsg : 'No embedding providers configured');
        }

        if (! $payload['primary_ok']) {
            $hadOk = collect($payload['results'])->contains(fn (array $r): bool => ($r['status'] ?? '') === 'ok');

            if ($hadOk) {
                $msg = __('healthcheck-ai::messages.embedding_chain.primary_failed_fallback_ok');

                return $result->warning(is_string($msg) ? $msg : 'Primary failed, fallback ok');
            }

            $allFailedMsg = __('healthcheck-ai::messages.embedding_chain.all_failed');

            return $result->failed(is_string($allFailedMsg) ? $allFailedMsg : 'All providers failed');
        }

        $okMsg = __('healthcheck-ai::messages.embedding_chain.ok');

        return $result->ok(is_string($okMsg) ? $okMsg : 'Primary embedding provider is healthy');
    }

    /**
     * @return array{results: list<array<string, mixed>>, primary_ok: bool, dimensions: int}
     */
    private function probe(): array
    {
        $configDimensions = config('healthcheck-ai.embedding_dimensions');
        $dimensions = $this->dimensions ?? (is_int($configDimensions) ? $configDimensions : 768);

        assert($this->resolveChainUsing instanceof Closure);

        /** @var list<array{provider: string, model: string}> $chain */
        $chain = ($this->resolveChainUsing)();
        $results = [];
        $primaryOk = false;

        if (empty($chain)) {
            return [
                'results' => [],
                'primary_ok' => false,
                'dimensions' => $dimensions,
            ];
        }

        $configCanary = config('healthcheck-ai.embedding_canary_text');
        $canary = is_string($configCanary) ? $configCanary : 'health check';

        foreach ($chain as $index => $step) {
            $provider = $step['provider'];
            $model = $step['model'];
            $startNs = hrtime(true);

            try {
                $response = Embeddings::for([$canary])
                    ->dimensions($dimensions)
                    ->generate($provider, $model);

                $latencyMs = round(((float) hrtime(true) - (float) $startNs) / 1_000_000, 1);
                $vector = $response->embeddings[0] ?? [];

                $status = (is_array($vector) && count($vector) === $dimensions) ? 'ok' : 'dimension_mismatch';
                $vectorDim = is_array($vector) ? count($vector) : 0;

                $results[] = [
                    'index' => $index,
                    'provider' => $provider,
                    'model' => $model,
                    'status' => $status,
                    'latency_ms' => $latencyMs,
                    'vector_dim' => $vectorDim,
                    'expected_dim' => $dimensions,
                ];

                if ($index === 0 && $status === 'ok') {
                    $primaryOk = true;
                }
            } catch (Throwable $e) {
                $latencyMs = round(((float) hrtime(true) - (float) $startNs) / 1_000_000, 1);
                $results[] = [
                    'index' => $index,
                    'provider' => $provider,
                    'model' => $model,
                    'status' => 'error',
                    'latency_ms' => $latencyMs,
                    'error' => mb_substr($e->getMessage(), 0, 256),
                ];
            }
        }

        return [
            'results' => $results,
            'primary_ok' => $primaryOk,
            'dimensions' => $dimensions,
        ];
    }
}
