<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowVersion;
use Flexpik\FilamentStudio\Flows\Triggers\CollectionEventTrigger;
use Flexpik\FilamentStudio\Flows\Triggers\CollectionEventTriggerConfig;
use Flexpik\FilamentStudio\Flows\Triggers\EventSubscriptionRegistry;

it('subscribes a flow whose trigger config is built from the declared schema', function () {
    $config = app(CollectionEventTriggerConfig::class);

    // Build the node config the way a UI or API client would: one entry per
    // declared field, seeded from defaults.
    $nodeConfig = [];
    foreach ($config->schema()['fields'] as $field) {
        $nodeConfig[$field['name']] = $config->defaults()[$field['name']] ?? null;
    }
    $nodeConfig[array_key_first($nodeConfig)] = 'tickets';

    $flow = StudioFlow::factory()->create();
    $version = StudioFlowVersion::factory()->for($flow, 'flow')->published()->create([
        'graph' => ['nodes' => [['id' => 'trigger', 'type' => 'trigger', 'data' => [
            'triggerType' => 'collection_event',
            'config' => $nodeConfig,
        ]]], 'edges' => []],
    ]);

    (new CollectionEventTrigger(app(EventSubscriptionRegistry::class)))->register($version);

    expect(app(EventSubscriptionRegistry::class)->matching('tickets', 'created'))
        ->toContain($flow->id);
});
