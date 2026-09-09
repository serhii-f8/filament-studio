<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Api\Flows\StudioFlowsApiRouteRegistrar;
use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;
use Flexpik\FilamentStudio\Flows\Jobs\ExecuteFlowJob;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowVersion;
use Flexpik\FilamentStudio\Flows\Security\Exceptions\InvalidWebhookSignatureException;
use Flexpik\FilamentStudio\Flows\Security\HmacWebhookVerifier;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    StudioFlowsApiRouteRegistrar::register();
    config()->set('filament-studio.flows.webhook_timestamp_window_seconds', 300);
});

it('refuses to verify against an empty secret even when the signature matches it', function () {
    $body = '{"a":1}';
    $ts = (string) time();
    $sig = hash_hmac('sha256', $ts.'.'.$body, '');

    expect(fn () => app(HmacWebhookVerifier::class)->verify($body, $sig, $ts, ''))
        ->toThrow(InvalidWebhookSignatureException::class);
});

it('returns 401 for an hmac flow whose webhook secret was never generated', function () {
    Bus::fake();

    $flow = StudioFlow::factory()->create([
        'slug' => 'no-secret',
        'status' => FlowStatus::Active,
        'webhook_auth_mode' => 'hmac',
        'webhook_secret' => null,
    ]);
    $version = StudioFlowVersion::factory()->for($flow, 'flow')->published()->create([
        'graph' => ['nodes' => [['id' => 'trigger', 'type' => 'trigger', 'data' => [
            'triggerType' => 'webhook',
            'config' => [],
        ]]], 'edges' => []],
    ]);
    $flow->forceFill(['published_version_id' => $version->id])->save();

    $body = '{"a":1}';
    $ts = (string) time();
    $sig = hash_hmac('sha256', $ts.'.'.$body, '');

    $this->call('POST', '/api/studio/webhooks/no-secret', [], [], [],
        ['HTTP_X_STUDIO_SIGNATURE' => $sig, 'HTTP_X_STUDIO_TIMESTAMP' => $ts, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertStatus(401);

    Bus::assertNotDispatched(ExecuteFlowJob::class);
});
