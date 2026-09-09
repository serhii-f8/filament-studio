<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Services\PublishFlowVersion;
use Flexpik\FilamentStudio\Flows\Services\RollbackFlowVersion;
use Flexpik\FilamentStudio\Flows\Services\SaveFlowDraft;

$graph = fn (string $tag) => [
    'nodes' => [
        ['id' => 't', 'type' => 'trigger', 'data' => ['triggerType' => 'manual']],
        ['id' => 'a', 'type' => 'operation', 'data' => ['key' => 'say', 'operationType' => 'log_message',
            'config' => ['level' => 'info', 'message' => $tag]]],
    ],
    'edges' => [['id' => 'e', 'source' => 't', 'target' => 'a', 'sourceHandle' => 'success']],
];

it('names draft saves, publishes and rollbacks distinctly in the audit log', function () use ($graph) {
    $flow = StudioFlow::factory()->active()->create();

    app(SaveFlowDraft::class)->save($flow, $graph('v1'));
    $v1 = app(PublishFlowVersion::class)->publish($flow->fresh());

    app(SaveFlowDraft::class)->save($flow->fresh(), $graph('v2'));
    app(PublishFlowVersion::class)->publish($flow->fresh());

    app(RollbackFlowVersion::class)->rollback($flow->fresh(), $v1->fresh());

    $events = $flow->auditLogs()->orderBy('id')->pluck('event')->all();

    expect($events)->toContain('draft_saved')
        ->and($events)->toContain('published')
        ->and($events)->toContain('rolled_back');
});
