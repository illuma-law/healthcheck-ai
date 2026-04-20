<?php

declare(strict_types=1);

use IllumaLaw\HealthCheckAi\AiEmbeddingChainHealthCheck;
use Laravel\Ai\Ai;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Spatie\Health\Enums\Status;

it('fails if chain resolver is missing', function () {
    $result = AiEmbeddingChainHealthCheck::new()->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->notificationMessage)->toContain('Missing chain resolver');
});

it('fails when no providers are configured', function () {
    $result = AiEmbeddingChainHealthCheck::new()
        ->resolveChainUsing(fn () => [])
        ->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->notificationMessage)->toContain('No embedding providers configured');
});

it('succeeds when primary provider is healthy', function () {
    Ai::fakeEmbeddings([
        new EmbeddingsResponse([array_fill(0, 768, 0.1)], 0, new Meta('openai', 'text-embedding-3-small')),
    ]);

    $result = AiEmbeddingChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'text-embedding-3-small'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::ok())
        ->and($result->shortSummary)->toBe('Primary OK');
});

it('warns when primary fails but fallback succeeds', function () {
    $called = 0;
    Ai::fakeEmbeddings(function () use (&$called) {
        $called++;
        if ($called === 1) {
            throw new Exception('Primary failed');
        }

        return new EmbeddingsResponse([array_fill(0, 768, 0.1)], 0, new Meta('gemini', 'ok'));
    });

    $result = AiEmbeddingChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'fail'],
            ['provider' => 'gemini', 'model' => 'ok'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::warning())
        ->and($result->shortSummary)->toBe('Primary degraded');
});

it('fails when all providers fail', function () {
    Ai::fakeEmbeddings(function () {
        throw new Exception('Failed');
    });

    $result = AiEmbeddingChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'fail1'],
            ['provider' => 'gemini', 'model' => 'fail2'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->shortSummary)->toBe('Primary degraded');
});

it('fails on dimension mismatch', function () {
    Ai::fakeEmbeddings([
        new EmbeddingsResponse([array_fill(0, 512, 0.1)], 0, new Meta('openai', 'wrong-dim')),
    ]);

    $result = AiEmbeddingChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'wrong-dim'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::failed());

    /** @var array<int, array<string, mixed>> $steps */
    $steps = $result->meta['steps'] ?? [];
    expect($steps[0]['status'])->toBe('dimension_mismatch');
});

it('can be configured with fluent methods', function () {
    Ai::fakeEmbeddings([
        new EmbeddingsResponse([array_fill(0, 512, 0.1)], 0, new Meta('openai', 'text-embedding-3-small')),
    ]);

    $check = AiEmbeddingChainHealthCheck::new()
        ->dimensions(512)
        ->cacheTtl(600)
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'text-embedding-3-small'],
        ]);

    $result = $check->run();
    expect($result->status)->toEqual(Status::ok())
        ->and($result->meta['dimensions'])->toBe(512);
});

it('fails when embeddings response is empty', function () {
    Ai::fakeEmbeddings([
        new EmbeddingsResponse([], 0, new Meta('openai', 'empty')),
    ]);

    $result = AiEmbeddingChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'empty'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::failed());
    /** @var array<int, array<string, mixed>> $steps */
    $steps = $result->meta['steps'] ?? [];
    expect($steps[0]['status'])->toBe('dimension_mismatch');
});
