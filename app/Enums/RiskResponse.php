<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Standard risk responses: accept, reduce, avoid, transfer.
 */
enum RiskResponse: string
{
    case Accept = 'accept';
    case Reduce = 'reduce';
    case Avoid = 'avoid';
    case Transfer = 'transfer';

    public function label(): string
    {
        return __('risk.response.'.$this->value);
    }
}
