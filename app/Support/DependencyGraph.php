<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pure (DB-free) helper for task dependency graphs.
 *
 * The graph is an adjacency map "task id => ids of its prerequisites".
 * An edge "A depends on B" is stored as $graph[A][] = B.
 */
final class DependencyGraph
{
    /**
     * Can $target be reached from $from by following prerequisite edges?
     * Iterative DFS (no recursion limit, safe for long chains).
     *
     * @param  array<int, list<int>>  $prerequisites
     */
    public static function reaches(array $prerequisites, int $from, int $target): bool
    {
        $stack = [$from];
        $seen = [];

        while ($stack !== []) {
            $node = array_pop($stack);

            if ($node === $target) {
                return true;
            }

            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;

            foreach ($prerequisites[$node] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $stack[] = $next;
                }
            }
        }

        return false;
    }

    /**
     * Would adding "$taskId depends on $dependsOnId" create a cycle?
     * Yes when it is a self-dependency, or when $dependsOnId already
     * (transitively) depends on $taskId.
     *
     * @param  array<int, list<int>>  $prerequisites
     */
    public static function wouldCreateCycle(array $prerequisites, int $taskId, int $dependsOnId): bool
    {
        return $taskId === $dependsOnId
            || self::reaches($prerequisites, $dependsOnId, $taskId);
    }
}
