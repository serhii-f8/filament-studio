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
        'slug' => 'form-hook',
        'status' => FlowStatus::Active,
        'webhook_auth_mode' => 'none',
    ]);
    $version = StudioFlowVersion::factory()->for($this->flow, 'flow')->published()->create([
        'graph' => ['nodes' => [['id' => 'trigger', 'type' => 'trigger', 'data' => [
            'triggerType' => 'webhook',
            'config' => [],
        ]]], 'edges' => []],
    ]);
    $this->flow->forceFill(['published_version_id' => $version->id])->save();
});

it('parses a form-encoded webhook body into an array on the trigger payload', function () {
    $body = 'subject=Form+encoded+ticket&priority=high';

    $this->call('POST', '/api/studio/webhooks/form-hook', [], [], [],
        ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        $body,
    )->assertStatus(202);

    $payload = StudioFlowRun::query()->firstOrFail()->trigger_payload;

    expect($payload['body'])->toBe([
        'subject' => 'Form encoded ticket',
        'priority' => 'high',
    ]);
});

it('keeps an unparseable body available as a raw string', function () {
    $body = 'not structured at all';

    $this->call('POST', '/api/studio/webhooks/form-hook', [], [], [],
        ['CONTENT_TYPE' => 'text/plain'],
        $body,
    )->assertStatus(202);

    $payload = StudioFlowRun::query()->firstOrFail()->trigger_payload;

    expect($payload['body'])->toBe('not structured at all')
        ->and($payload['raw'])->toBe('not structured at all');
});
