<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * قرعه‌کشی قابل بازتولید.
 *
 *  - random: ترتیب تصادفی با seed (ورودی ابتدا بر اساس id مرتب می‌شود ← نتیجه مستقل از ترتیب ورودی).
 *  - seeded: بر اساس شماره‌ی سید (null در انتها) و سپس id؛ تصادفی در کار نیست.
 */
final class DrawEngine
{
    public const METHODS = ['random', 'seeded'];

    /**
     * @param  list<array{id: int, seed?: int|null}>  $items
     * @return array{method: string, seed: string, order: list<int>}
     */
    public function draw(array $items, string $method, ?string $seed = null): array
    {
        if (! in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException("Unsupported draw method [{$method}].");
        }

        $seed = $seed !== null && $seed !== '' ? $seed : SeededRandom::newSeed();

        $items = array_values($items);
        usort($items, fn (array $x, array $y): int => $x['id'] <=> $y['id']);

        if ($method === 'seeded') {
            usort($items, function (array $x, array $y): int {
                $sx = $x['seed'] ?? PHP_INT_MAX;
                $sy = $y['seed'] ?? PHP_INT_MAX;

                return $sx === $sy ? $x['id'] <=> $y['id'] : $sx <=> $sy;
            });

            $order = array_map(fn (array $i): int => $i['id'], $items);
        } else {
            $order = (new SeededRandom($seed))->shuffle(array_map(fn (array $i): int => $i['id'], $items));
        }

        return ['method' => $method, 'seed' => $seed, 'order' => array_values($order)];
    }
}
