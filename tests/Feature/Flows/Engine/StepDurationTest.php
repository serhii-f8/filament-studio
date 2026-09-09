<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Engine\FlowDispatcher;
use Flexpik\FilamentStudio\Flows\Enums\FlowRunStatus;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Operations\OperationRegistry;
use Flexpik\FilamentStudio\Flows\Services\PublishFlowVersion;
use Flexpik\FilamentStudio\Tests\Fixtures\Flows\SlowOperation;

beforeEach(function () {
    app(OperationRegistry::class)->register('slow_test_op', 'Slow test op', SlowOperation::class);
});

it('records each step duration in milliseconds at sub-second resolution', function () {
    $flow = StudioFlow::factory()->active()->create(['slug' => 'step-duration']);
    $flow->update([
        'draft_graph' => [
            'nodes' => [
                ['id' => 't', 'type' => 'trigger', 'data' => ['triggerType' => 'manual']],
                ['id' => 's', 'type' => 'operation', 'data' => [
                    'key' => 'slow', 'operationType' => 'slow_test_op', 'config' => [],
                ]],
            ],
            'edges' => [['id' => 'e', 'source' => 't', 'target' => 's', 'sourceHandle' => 'success']],
        ],
    ]);
    app(PublishFlowVersion::class)->publish($flow->fresh());

    $run = app(FlowDispatcher::class)->dispatchSync(
        flow: $flow->fresh(),
        triggerType: 'manual',
        payload: [],
        accountability: ['user_id' => null, 'tenant_id' => null, 'role' => null, 'source' => 'manual'],
    );

    expect($run->status)->toBe(FlowRunStatus::Completed);

    $step = $run->steps()->firstOrFail();
    $sleptMs = (int) (SlowOperation::SLEEP_MICROSECONDS / 1000);

    expect($step->duration_ms)->toBeInt()->toBeGreaterThanOrEqual($sleptMs);
});
