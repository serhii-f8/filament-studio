<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Engine\FlowDispatcher;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Services\PublishFlowVersion;

function publishGraph(StudioFlow $flow, array $graph): StudioFlow
{
    $flow->update(['draft_graph' => $graph]);
    app(PublishFlowVersion::class)->publish($flow->fresh());

    return $flow->fresh();
}

it('routes a synchronous child-flow failure down the failure branch', function () {
    $child = publishGraph(StudioFlow::factory()->active()->create(), [
        'nodes' => [
            ['id' => 'ct', 'type' => 'trigger', 'data' => ['triggerType' => 'manual']],
            ['id' => 'cb', 'type' => 'operation', 'data' => ['key' => 'boom', 'operationType' => 'create_record',
                'config' => ['collection' => 'no-such-collection', 'data' => []]]],
        ],
        'edges' => [['id' => 'ce', 'source' => 'ct', 'target' => 'cb', 'sourceHandle' => 'success']],
    ]);

    $parent = publishGraph(StudioFlow::factory()->active()->create(), [
        'nodes' => [
            ['id' => 'pt', 'type' => 'trigger', 'data' => ['triggerType' => 'manual']],
            ['id' => 'pc', 'type' => 'operation', 'data' => ['key' => 'call_child', 'operationType' => 'trigger_flow',
                'config' => ['flow_id' => $child->id, 'mode' => 'sync']]],
            ['id' => 'pf', 'type' => 'operation', 'data' => ['key' => 'on_child_failure', 'operationType' => 'log_message',
                'config' => ['level' => 'warning', 'message' => 'child failed']]],
        ],
        'edges' => [
            ['id' => 'pe1', 'source' => 'pt', 'target' => 'pc', 'sourceHandle' => 'success'],
            ['id' => 'pe2', 'source' => 'pc', 'target' => 'pf', 'sourceHandle' => 'failure'],
        ],
    ]);

    $run = app(FlowDispatcher::class)->dispatchSync($parent, 'manual', [],
        ['user_id' => null, 'tenant_id' => null, 'role' => null, 'source' => 'manual']);

    $callStep = $run->steps()->where('operation_key', 'call_child')->firstOrFail();

    expect($callStep->branch_taken)->toBe('failure')
        ->and($run->steps()->where('operation_key', 'on_child_failure')->exists())->toBeTrue();
});
