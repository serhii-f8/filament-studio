<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Engine\FlowDispatcher;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowSecret;
use Flexpik\FilamentStudio\Flows\Services\PublishFlowVersion;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;

const SECRET_VALUE = 'sk-live-do-not-log-me';

beforeEach(function () {
    $this->collection = StudioCollection::factory()->create(['slug' => 'calls', 'tenant_id' => null]);
    StudioField::factory()->for($this->collection, 'collection')->create(['column_name' => 'sent_token', 'field_type' => 'text']);

    $this->flow = StudioFlow::factory()->active()->create();
    StudioFlowSecret::create(['flow_id' => $this->flow->id, 'key' => 'API_TOKEN', 'value' => SECRET_VALUE]);
});

/** @param array<string, mixed> $data */
function runFlowWriting(StudioFlow $flow, array $data): object
{
    $flow->update(['draft_graph' => [
        'nodes' => [
            ['id' => 't', 'type' => 'trigger', 'data' => ['triggerType' => 'manual']],
            ['id' => 'c', 'type' => 'operation', 'data' => ['key' => 'call', 'operationType' => 'create_record',
                'config' => ['collection' => 'calls', 'data' => $data]]],
        ],
        'edges' => [['id' => 'e', 'source' => 't', 'target' => 'c', 'sourceHandle' => 'success']],
    ]]);
    app(PublishFlowVersion::class)->publish($flow->fresh());

    return app(FlowDispatcher::class)->dispatchSync($flow->fresh(), 'manual', [],
        ['user_id' => null, 'tenant_id' => null, 'role' => null, 'source' => 'manual']);
}

it('resolves a flow secret into an operation config', function () {
    runFlowWriting($this->flow, ['sent_token' => '{{ $secrets.API_TOKEN }}']);

    $row = EavQueryBuilder::for($this->collection)->get()->first();

    expect($row->sent_token)->toBe(SECRET_VALUE);
});

it('never writes a resolved secret value into the persisted step log', function () {
    // "note" is not a sensitive-looking key, so key-pattern masking cannot save us here.
    StudioField::factory()->for($this->collection, 'collection')->create(['column_name' => 'note', 'field_type' => 'text']);

    $run = runFlowWriting($this->flow, ['note' => 'calling with {{ $secrets.API_TOKEN }}']);

    $step = $run->steps()->firstOrFail();
    $logged = json_encode([$step->input, $step->output]);

    expect($logged)->not->toContain(SECRET_VALUE);
});

it('leaves an unknown secret key empty rather than exposing the bag', function () {
    runFlowWriting($this->flow, ['sent_token' => '[{{ $secrets.NOT_A_SECRET }}]']);

    $row = EavQueryBuilder::for($this->collection)->get()->first();

    expect($row->sent_token)->toBe('[]');
});

it('does not leak one flow\'s secrets into another flow', function () {
    $other = StudioFlow::factory()->active()->create();

    runFlowWriting($other, ['sent_token' => '[{{ $secrets.API_TOKEN }}]']);

    $row = EavQueryBuilder::for($this->collection)->get()->first();

    expect($row->sent_token)->toBe('[]');
});
