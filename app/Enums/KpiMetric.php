<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * شاخص‌های سند: آگاهی، دسترسی، تعامل.
 */
enum KpiMetric: string
{
    case Awareness = 'awareness';
    case Reach = 'reach';
    case Engagement = 'engagement';

    public function label(): string
    {
        return __('marketing.kpi_metric.'.$this->value);
    }
}
