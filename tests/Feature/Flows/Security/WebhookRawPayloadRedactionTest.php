<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Api\Flows\StudioFlowsApiRouteRegistrar;
use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowRun;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowVersion;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    StudioFlowsApiRouteRegistrar::register();
    Bus::fake();

    $this->flow = StudioFlow::factory()->create([
        'slug' => 'redacting-hook',
        'status' => FlowStatus::Active,
        'webhook_auth_mode' => 'none',
        'webhook_redact_paths' => ['body/card/number'],
    ]);
    $version = StudioFlowVersion::factory()->for($this->flow, 'flow')->published()->create([
        'graph' => ['nodes' => [['id' => 'trigger', 'type' => 'trigger', 'data' => [
            'triggerType' => 'webhook', 'config' => [],
        ]]], 'edges' => []],
    ]);
    $this->flow->forceFill(['published_version_id' => $version->id])->save();
});

it('keeps redacted and masked values out of the stored raw body', function () {
    $body = '{"card":{"number":"4111111111111111"},"password":"hunter2"}';

    $this->call('POST', '/api/studio/webhooks/redacting-hook', [], [], [],
        ['CONTENT_TYPE' => 'application/json'], $body,
    )->assertStatus(202);

    $payload = StudioFlowRun::query()->firstOrFail()->trigger_payload;

    expect($payload['body']['card']['number'])->toBe('***')
        ->and($payload['body']['password'])->toBe('***')
        ->and($payload['raw'])->not->toContain('4111111111111111')
        ->and($payload['raw'])->not->toContain('hunter2');
});
