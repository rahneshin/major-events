<?php

use App\Support\DependencyGraph;

// graph[A] = [B] means "A depends on B"

it('detects a self dependency', function () {
    expect(DependencyGraph::wouldCreateCycle([], 1, 1))->toBeTrue();
});

it('detects a direct two-node cycle', function () {
    // 1 already depends on 2; adding "2 depends on 1" closes the loop
    expect(DependencyGraph::wouldCreateCycle([1 => [2]], 2, 1))->toBeTrue();
});

it('detects an indirect cycle through a long chain', function () {
    // 1 -> 2 -> 3 -> 4 (1 depends on 2, 2 on 3, 3 on 4); adding "4 depends on 1" closes it
    $graph = [1 => [2], 2 => [3], 3 => [4]];

    expect(DependencyGraph::wouldCreateCycle($graph, 4, 1))->toBeTrue()
        ->and(DependencyGraph::wouldCreateCycle($graph, 4, 2))->toBeTrue()
        ->and(DependencyGraph::wouldCreateCycle($graph, 3, 1))->toBeTrue();
});

it('allows legitimate edges, including diamonds', function () {
    // 1 depends on 2 and 3; both depend on 4 (a diamond is NOT a cycle)
    $graph = [1 => [2, 3], 2 => [4], 3 => [4]];

    expect(DependencyGraph::wouldCreateCycle($graph, 5, 1))->toBeFalse()
        ->and(DependencyGraph::wouldCreateCycle($graph, 1, 4))->toBeFalse()
        ->and(DependencyGraph::wouldCreateCycle($graph, 2, 3))->toBeFalse();
});

it('terminates on an already-cyclic graph (corrupt data)', function () {
    $graph = [1 => [2], 2 => [1]];

    expect(DependencyGraph::reaches($graph, 1, 99))->toBeFalse();
});

it('handles a very long chain without recursion limits', function () {
    $graph = [];
    for ($i = 1; $i < 5000; $i++) {
        $graph[$i] = [$i + 1];
    }

    expect(DependencyGraph::wouldCreateCycle($graph, 5000, 1))->toBeTrue()
        ->and(DependencyGraph::wouldCreateCycle($graph, 1, 5000))->toBeFalse();
});
