<?php

declare(strict_types=1);

namespace App\Enums;

enum SponsorCategory: string
{
    case Banking = 'banking';
    case Telecom = 'telecom';
    case Automotive = 'automotive';
    case FoodBeverage = 'food_beverage';
    case Sportswear = 'sportswear';
    case Health = 'health';
    case Technology = 'technology';
    case Retail = 'retail';
    case Media = 'media';
    case Government = 'government';
    case Other = 'other';

    public function label(): string
    {
        return __('sponsorship.category.'.$this->value);
    }
}
