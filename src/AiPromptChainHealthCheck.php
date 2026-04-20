<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi;

use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AnonymousAgent;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;
use Throwable;

final class AiPromptChainHealthCheck extends Check
{
    private const string CACHE_KEY = 'health:ai:prompt_chain:v1';

    /** @var \Closure|null */
    private $resolveChainUsing = null;

    private ?int $cacheTtl = null;

    private ?int $timeoutSeconds = null;

    public function resolveChainUsing(Closure $callback): self
    {
        $this->resolveChainUsing = $callback;

        return $this;
    }

    public function cacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeoutSeconds = $seconds;

        return $this;
    }

    public function run(): Result
    {
        if (! $this->resolveChainUsing) {
            return Result::make()->failed('Missing chain resolver for AiPromptChainHealthCheck');
        }

        $configTtl = config('healthcheck-ai.prompt_cache_ttl_seconds');
        $ttl = $this->cacheTtl ?? (is_int($configTtl) ? $configTtl : 300);

        /** @var array{skipped: bool, reason: string|null, primary_ok: bool, winner: array<string, mixed>|null, error: string|null} $payload */
        $payload = Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->probe());

        if ($payload['skipped']) {
            return (new Result(Status::skipped(), (string) ($payload['reason'] ?? 'Prompt chain probe skipped.')))
                ->meta(['cached' => true, 'cache_ttl_seconds' => $ttl])
                ->shortSummary('Skipped');
        }

        $meta = [
            'cached' => true,
            'cache_ttl_seconds' => $ttl,
            'winner' => $payload['winner'],
        ];

        if ($payload['error']) {
            return Result::make()
                ->meta($meta)
                ->shortSummary('Failed')
                ->failed($payload['error']);
        }

        $result = Result::make()
            ->meta($meta)
            ->shortSummary($payload['primary_ok'] ? 'Primary OK' : 'Degraded');

        if (! $payload['primary_ok']) {
            $msg = __('healthcheck-ai::messages.prompt_chain.primary_failed_fallback_ok');

            return $result->warning(is_string($msg) ? $msg : 'Primary failed, fallback ok');
        }

        $okMsg = __('healthcheck-ai::messages.prompt_chain.ok');

        return $result->ok(is_string($okMsg) ? $okMsg : 'Primary text provider is healthy');
    }

    /**
     * @return array{skipped: bool, reason: string|null, primary_ok: bool, winner: array<string, mixed>|null, error: string|null}
     */
    private function probe(): array
    {
        assert($this->resolveChainUsing instanceof \Closure);

        /** @var list<array{provider: string, model: string}> $chain */
        $chain = ($this->resolveChainUsing)();

        if (count($chain) === 0) {
            return [
                'skipped' => true,
                'reason' => 'No AI failover chain is configured.',
                'primary_ok' => false,
                'winner' => null,
                'error' => null,
            ];
        }

        $configPrompt = config('healthcheck-ai.prompt_text');
        $prompt = is_string($configPrompt) ? $configPrompt : 'Reply with exactly: OK';

        $configTimeout = config('healthcheck-ai.prompt_timeout_seconds');
        $timeout = $this->timeoutSeconds ?? (is_int($configTimeout) ? $configTimeout : 25);

        $payload = null;

        foreach ($chain as $index => $step) {
            try {
                $agent = new AnonymousAgent('You are a terse health probe. Follow the user instruction exactly.', [], []);
                $agent->prompt($prompt, [], $step['provider'], $step['model'], $timeout);

                $payload = [
                    'skipped' => false,
                    'reason' => null,
                    'primary_ok' => $index === 0,
                    'winner' => [
                        'provider' => $step['provider'],
                        'model' => $step['model'],
                    ],
                    'error' => null,
                ];

                break;
            } catch (Throwable $e) {
                if ($index === count($chain) - 1) {
                    $payload = [
                        'skipped' => false,
                        'reason' => null,
                        'primary_ok' => false,
                        'winner' => null,
                        'error' => mb_substr($e->getMessage(), 0, 400),
                    ];
                }
            }
        }

        return $payload ?? [
            'skipped' => true,
            'reason' => 'No AI failover chain is configured.',
            'primary_ok' => false,
            'winner' => null,
            'error' => null,
        ];
    }
}
