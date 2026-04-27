<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi;

use Closure;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use ReflectionClass;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class AiAgentRegistryCheck extends Check
{
    private ?Closure $resolveAgentsUsing = null;

    private ?Closure $hasCredentialsUsing = null;

    public function resolveAgentsUsing(Closure $callback): self
    {
        $this->resolveAgentsUsing = $callback;

        return $this;
    }

    public function hasCredentialsUsing(Closure $callback): self
    {
        $this->hasCredentialsUsing = $callback;

        return $this;
    }

    public function run(): Result
    {
        if (! $this->resolveAgentsUsing || ! $this->hasCredentialsUsing) {
            return Result::make()->failed('Missing required resolvers for AiAgentRegistryCheck');
        }

        /** @var list<class-string> $agents */
        $agents = ($this->resolveAgentsUsing)();
        $missingCredentials = [];
        $missingModel = [];
        $withoutProvider = [];

        foreach ($agents as $agentClass) {
            $ref = new ReflectionClass($agentClass);
            $providerAttrs = $ref->getAttributes(Provider::class);

            if ($providerAttrs === []) {
                $withoutProvider[] = $agentClass;

                continue;
            }

            $providerValue = $providerAttrs[0]->newInstance()->value;
            $provider = is_scalar($providerValue) ? (string) $providerValue : 'unknown';

            if (! ($this->hasCredentialsUsing)($provider)) {
                $missingCredentials[] = "{$agentClass} ({$provider})";

                continue;
            }

            $modelAttrs = $ref->getAttributes(Model::class);
            if ($modelAttrs === []) {
                $missingModel[] = $agentClass;
            }
        }

        $meta = [
            'agents_checked' => count($agents),
            'without_provider_attribute' => array_slice($withoutProvider, 0, 20),
            'missing_model_attribute' => array_slice($missingModel, 0, 20),
        ];

        $result = Result::make()->meta($meta);

        if ($missingCredentials !== []) {
            $message = __('healthcheck-ai::messages.agent_registry.missing_credentials', [
                'agents' => implode('; ', array_slice($missingCredentials, 0, 6)).(count($missingCredentials) > 6 ? '…' : ''),
            ]);

            return $result
                ->failed(is_string($message) ? $message : 'Missing AI credentials')
                ->shortSummary(count($missingCredentials).' issue(s)');
        }

        if ($missingModel !== []) {
            $message = __('healthcheck-ai::messages.agent_registry.missing_model', [
                'agents' => implode('; ', array_slice($missingModel, 0, 6)).(count($missingModel) > 6 ? '…' : ''),
            ]);

            return $result
                ->warning(is_string($message) ? $message : 'Missing model attributes')
                ->shortSummary(count($missingModel).' hint(s)');
        }

        $okMessage = __('healthcheck-ai::messages.agent_registry.ok');

        return $result
            ->ok(is_string($okMessage) ? $okMessage : 'Agent registry is healthy')
            ->shortSummary('OK');
    }
}
