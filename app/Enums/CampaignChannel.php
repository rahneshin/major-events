<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignChannel: string
{
    case Social = 'social';
    case Website = 'website';
    case Sms = 'sms';
    case Email = 'email';
    case Press = 'press';
    case TvRadio = 'tv_radio';
    case Outdoor = 'outdoor';
    case Influencer = 'influencer';
    case Onsite = 'onsite';
    case Other = 'other';

    public function label(): string
    {
        return __('marketing.channel.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
