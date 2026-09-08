<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Engine\GraphWalker;

it('resolves successors filtered by sourceHandle', function () {
    $graph = [
        'nodes' => [
            ['id' => 'cond', 'type' => 'operation'],
            ['id' => 'yes', 'type' => 'operation'],
            ['id' => 'no', 'type' => 'operation'],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'cond', 'target' => 'yes', 'sourceHandle' => 'success'],
            ['id' => 'e2', 'source' => 'cond', 'target' => 'no', 'sourceHandle' => 'failure'],
        ],
    ];

    $walker = new GraphWalker;
    expect($walker->successors('cond', 'success', $graph))->toBe(['yes']);
    expect($walker->successors('cond', 'failure', $graph))->toBe(['no']);
    expect($walker->successors('cond', null, $graph))->toBe(['yes', 'no']);
});

it('treats an edge without a sourceHandle as a success edge', function () {
    $graph = [
        'nodes' => [
            ['id' => 'trigger', 'type' => 'trigger'],
            ['id' => 'op', 'type' => 'operation'],
        ],
        // Edges built by hand, by a seeder, or through the API often omit
        // sourceHandle; they must still be followed on the success branch.
        'edges' => [
            ['id' => 'e1', 'source' => 'trigger', 'target' => 'op'],
        ],
    ];

    $walker = new GraphWalker;
    expect($walker->successors('trigger', 'success', $graph))->toBe(['op']);
    expect($walker->successors('trigger', 'failure', $graph))->toBe([]);
    expect($walker->successors('trigger', null, $graph))->toBe(['op']);
});

it('treats an explicitly null sourceHandle as a success edge', function () {
    $graph = [
        'nodes' => [
            ['id' => 'a', 'type' => 'operation'],
            ['id' => 'b', 'type' => 'operation'],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'a', 'target' => 'b', 'sourceHandle' => null],
        ],
    ];

    $walker = new GraphWalker;
    expect($walker->successors('a', 'success', $graph))->toBe(['b']);
    expect($walker->successors('a', 'failure', $graph))->toBe([]);
});
