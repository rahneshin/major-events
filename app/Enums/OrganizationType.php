<?php

namespace App\Enums;

enum OrganizationType: string
{
    case Federation = 'federation';
    case Club = 'club';
    case Academy = 'academy';
    case University = 'university';
    case Municipality = 'municipality';
    case Company = 'company';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Federation => __('org.type_federation'),
            self::Club => __('org.type_club'),
            self::Academy => __('org.type_academy'),
            self::University => __('org.type_university'),
            self::Municipality => __('org.type_municipality'),
            self::Company => __('org.type_company'),
            self::Other => __('org.type_other'),
        };
    }
}
