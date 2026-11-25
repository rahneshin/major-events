<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * PRNG قطعی از یک seed متنی (زنجیره‌ی SHA-256 + rejection sampling).
 *
 * همان seed ← همان دنباله‌ی اعداد، روی هر ماشین و هر نسخه‌ی PHP.
 * پایه‌ی قرعه‌کشی قابل بازتولید و ممیزی است؛ هیچ وابستگی به Laravel یا دیتابیس ندارد.
 */
final class SeededRandom
{
    private string $state;

    private int $counter = 0;

    private string $buffer = '';

    public function __construct(private readonly string $seed)
    {
        $this->state = hash('sha256', 'seed:'.$seed, true);
    }

    public static function newSeed(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function seed(): string
    {
        return $this->seed;
    }

    /**
     * عدد صحیح یکنواخت در بازه‌ی بسته‌ی [min, max].
     */
    public function nextInt(int $min, int $max): int
    {
        if ($max < $min) {
            throw new \InvalidArgumentException('max must be >= min');
        }

        $range = $max - $min + 1;

        if ($range === 1) {
            return $min;
        }

        // 2^32 مقدار ممکن؛ مقادیر بالای بزرگ‌ترین مضرب range کنار گذاشته می‌شوند (بدون سوگیری)
        $space = 4294967296;
        $limit = intdiv($space, $range) * $range;

        do {
            $value = $this->nextUint32();
        } while ($value >= $limit);

        return $min + ($value % $range);
    }

    /**
     * Fisher–Yates؛ ورودی تغییر نمی‌کند.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        $items = array_values($items);

        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $this->nextInt(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    private function nextUint32(): int
    {
        if (strlen($this->buffer) < 4) {
            $this->state = hash('sha256', $this->state.pack('N', $this->counter++), true);
            $this->buffer .= $this->state;
        }

        $chunk = substr($this->buffer, 0, 4);
        $this->buffer = (string) substr($this->buffer, 4);

        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', $chunk);

        return $unpacked[1];
    }
}
