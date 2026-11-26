<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * تقسیم شرکت‌کننده‌ها به گروه (Step 12D). PHP خالص، قطعی و بدون DB.
 *
 *  - seeded: ورودی به ترتیب سید است (سید ۱ اول)؛ پخش «مارپیچی» (snake): A B C D | D C B A | A B C D …
 *    تا قوی‌ها در یک گروه جمع نشوند.
 *  - random: ورودی همان ترتیب قرعه‌ی تصادفی است؛ پخش دور‌به‌دور (A B C D | A B C D …).
 *
 * اندازه‌ی گروه‌ها حداکثر یک نفر با هم فرق دارد.
 */
final class GroupSplitter
{
    public const METHODS = ['random', 'seeded'];

    public const MAX_GROUPS = 16;

    public const MIN_GROUP_SIZE = 2;

    /**
     * @param  list<int>  $order  شناسه‌ها به ترتیب قرعه
     * @return list<list<int>> گروه‌ها؛ اندیس ۰ = گروه A
     */
    public function split(array $order, int $groups, string $method): array
    {
        if ($groups < 2 || $groups > self::MAX_GROUPS) {
            throw new \InvalidArgumentException('Groups must be between 2 and '.self::MAX_GROUPS.'.');
        }

        if (! in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException("Unsupported split method [{$method}].");
        }

        $order = array_values($order);

        if (count($order) < $groups * self::MIN_GROUP_SIZE) {
            throw new \InvalidArgumentException('Every group needs at least '.self::MIN_GROUP_SIZE.' participants.');
        }

        /** @var list<list<int>> $result */
        $result = array_fill(0, $groups, []);

        foreach ($order as $i => $id) {
            $row = intdiv($i, $groups);
            $column = $i % $groups;

            if ($method === 'seeded' && $row % 2 === 1) {
                $column = $groups - 1 - $column;
            }

            $result[$column][] = $id;
        }

        return $result;
    }

    /**
     * نام گروه: ۰ → A، ۱ → B، …
     */
    public function name(int $index): string
    {
        return chr(65 + $index);
    }
}
