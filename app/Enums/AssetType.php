<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetType: string
{
    case Naming = 'naming';
    case Signage = 'signage';
    case Digital = 'digital';
    case Booth = 'booth';
    case Hospitality = 'hospitality';
    case Media = 'media';
    case Other = 'other';

    public function label(): string
    {
        return __('sponsorship.asset_type.'.$this->value);
    }
}
