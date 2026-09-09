<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Api\Flows\StudioFlowsApiRouteRegistrar;
use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;
use Flexpik\FilamentStudio\Flows\Jobs\ExecuteFlowJob;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowVersion;
use Flexpik\FilamentStudio\Flows\Triggers\WebhookTriggerConfig;
use Illuminate\Support\Facades\Bus;

it('declares no node-level config fields, because webhook auth is a flow-level setting', function () {
    $config = app(WebhookTriggerConfig::class);

    expect($config->schema()['fields'])->toBe([])
        ->and($config->defaults())->toBe([]);
});

it('cannot be weakened by an auth_mode left on the trigger node', function () {
    StudioFlowsApiRouteRegistrar::register();
    Bus::fake();

    $flow = StudioFlow::factory()->create([
        'slug' => 'node-says-none',
        'status' => FlowStatus::Active,
        'webhook_auth_mode' => 'hmac',
        'webhook_secret' => 'a-real-secret',
    ]);
    $version = StudioFlowVersion::factory()->for($flow, 'flow')->published()->create([
        'graph' => ['nodes' => [['id' => 'trigger', 'type' => 'trigger', 'data' => [
            'triggerType' => 'webhook',
            'config' => ['auth_mode' => 'none'],
        ]]], 'edges' => []],
    ]);
    $flow->forceFill(['published_version_id' => $version->id])->save();

    $this->postJson('/api/studio/webhooks/node-says-none', ['a' => 1])->assertStatus(401);

    Bus::assertNotDispatched(ExecuteFlowJob::class);
});
