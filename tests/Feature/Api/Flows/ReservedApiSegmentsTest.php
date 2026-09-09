<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Api\Flows\StudioFlowsApiRouteRegistrar;
use Flexpik\FilamentStudio\Api\StudioApiRouteRegistrar;
use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowVersion;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Registration order matches the service provider: collection routes first.
    // The collection routes are a two-segment catch-all, so without reserved
    // segments they swallow the flows API and the webhook endpoint.
    StudioApiRouteRegistrar::register();
    StudioFlowsApiRouteRegistrar::register();
});

it('does not let the collection catch-all swallow the flows API', function () {
    $this->getJson('/api/studio/flows');

    // Both guards answer 401, so assert on which controller actually matched.
    expect(app('router')->getCurrentRoute()?->getActionName())
        ->toContain('Api\\Flows\\Controllers\\FlowController');
});

it('does not let the collection catch-all swallow the webhook endpoint', function () {
    Bus::fake();

    $flow = StudioFlow::factory()->create([
        'slug' => 'reserved-probe',
        'status' => FlowStatus::Active,
        'webhook_auth_mode' => 'none',
    ]);
    $version = StudioFlowVersion::factory()->for($flow, 'flow')->published()->create([
        'graph' => ['nodes' => [['id' => 't', 'type' => 'trigger', 'data' => ['triggerType' => 'webhook', 'config' => []]]], 'edges' => []],
    ]);
    $flow->forceFill(['published_version_id' => $version->id])->save();

    $this->postJson('/api/studio/webhooks/reserved-probe', ['a' => 1])->assertStatus(202);

    // A GET on the webhook URI is not a collection read — the webhook route
    // exists for POST only, so the correct answer is 405, not the collection
    // guard's 401.
    $this->getJson('/api/studio/webhooks/reserved-probe')->assertStatus(405);
});
