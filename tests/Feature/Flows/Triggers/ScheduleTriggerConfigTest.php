<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Services\PublishFlowVersion;
use Flexpik\FilamentStudio\Flows\Triggers\Schedule\ScheduleTriggerConfig;

/** Build the node config the way a UI or API client would: one entry per declared field. */
function scheduleNodeConfigFromSchema(): array
{
    $config = app(ScheduleTriggerConfig::class);
    $nodeConfig = [];

    foreach ($config->schema()['fields'] as $field) {
        $nodeConfig[$field['name']] = $config->defaults()[$field['name']] ?? null;
    }

    return $nodeConfig;
}

function scheduleFlowWithDraft(array $nodeConfig): StudioFlow
{
    $flow = StudioFlow::factory()->create(['status' => FlowStatus::Active]);

    $flow->update(['draft_graph' => [
        'nodes' => [
            ['id' => 't', 'type' => 'trigger', 'data' => ['triggerType' => 'schedule', 'config' => $nodeConfig]],
            ['id' => 'a', 'type' => 'operation', 'data' => ['key' => 'tick', 'operationType' => 'log_message', 'config' => ['level' => 'info', 'message' => 'tick']]],
        ],
        'edges' => [['id' => 'e', 'source' => 't', 'target' => 'a', 'sourceHandle' => 'success']],
    ]]);

    return $flow->fresh();
}

it('publishes a schedule flow whose trigger config is built from the declared schema', function () {
    $flow = scheduleFlowWithDraft(scheduleNodeConfigFromSchema());

    app(PublishFlowVersion::class)->publish($flow);

    expect($flow->fresh()->published_version_id)->not->toBeNull();
});

it('dispatches a schedule flow configured from the declared schema when it is due', function () {
    $nodeConfig = scheduleNodeConfigFromSchema();
    $nodeConfig[array_key_first($nodeConfig)] = '* * * * *';

    $flow = scheduleFlowWithDraft($nodeConfig);
    app(PublishFlowVersion::class)->publish($flow);

    $this->artisan('studio:flows:dispatch-scheduled')->assertSuccessful();

    expect($flow->fresh()->runs()->count())->toBe(1);
});
