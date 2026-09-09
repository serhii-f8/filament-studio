<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Services\PublishFlowVersion;
use Flexpik\FilamentStudio\Flows\Triggers\EventSubscriptionRegistry;
use Illuminate\Support\Facades\Cache;

function publishCollectionEventFlow(string $collection, array $events): StudioFlow
{
    $flow = StudioFlow::factory()->create(['status' => FlowStatus::Active]);

    $flow->update(['draft_graph' => [
        'nodes' => [
            ['id' => 't', 'type' => 'trigger', 'data' => ['triggerType' => 'collection_event',
                'config' => ['collection' => $collection, 'events' => $events]]],
            ['id' => 'a', 'type' => 'operation', 'data' => ['key' => 'noop', 'operationType' => 'log_message',
                'config' => ['level' => 'info', 'message' => 'x']]],
        ],
        'edges' => [['id' => 'e', 'source' => 't', 'target' => 'a', 'sourceHandle' => 'success']],
    ]]);
    app(PublishFlowVersion::class)->publish($flow->fresh());

    return $flow->fresh();
}

it('still matches a published flow after the cache is flushed', function () {
    $flow = publishCollectionEventFlow('people', ['created']);

    expect(app(EventSubscriptionRegistry::class)->matching('people', 'created'))->toContain($flow->id);

    // A deploy running `php artisan optimize:clear`, an eviction, a restarted
    // cache server — "forever" in a cache store is not durability.
    Cache::flush();

    expect(app(EventSubscriptionRegistry::class)->matching('people', 'created'))->toContain($flow->id);
});

it('rebuilds only the events each published version actually declares', function () {
    $flow = publishCollectionEventFlow('orders', ['updated']);

    Cache::flush();

    $registry = app(EventSubscriptionRegistry::class);

    expect($registry->matching('orders', 'updated'))->toContain($flow->id)
        ->and($registry->matching('orders', 'created'))->not->toContain($flow->id)
        ->and($registry->matching('people', 'updated'))->not->toContain($flow->id);
});

it('does not resurrect a flow whose trigger is not a collection event', function () {
    $flow = StudioFlow::factory()->create(['status' => FlowStatus::Active]);
    $flow->update(['draft_graph' => [
        'nodes' => [['id' => 't', 'type' => 'trigger', 'data' => ['triggerType' => 'webhook', 'config' => []]]],
        'edges' => [],
    ]]);
    app(PublishFlowVersion::class)->publish($flow->fresh());

    Cache::flush();

    expect(app(EventSubscriptionRegistry::class)->all())->not->toHaveKey($flow->id);
});
