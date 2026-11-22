<?php

declare(strict_types=1);

namespace App\Enums;

enum RiskStatus: string
{
    case Open = 'open';
    case Mitigating = 'mitigating';
    case Closed = 'closed';
    case Occurred = 'occurred';

    public function label(): string
    {
        return __('risk.status.'.$this->value);
    }

    /**
     * Still being managed: shown on the matrix and counted in the summary.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Mitigating], true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Open->value, self::Mitigating->value];
    }
}
