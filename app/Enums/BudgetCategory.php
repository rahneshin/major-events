<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * دسته‌های پیش‌فرض بودجه (Step 09).
 * `OtherRevenue` به فهرست سند اضافه شده تا درآمد دسته‌بندی‌نشده جا داشته باشد.
 */
enum BudgetCategory: string
{
    // هزینه
    case Venue = 'venue';
    case Equipment = 'equipment';
    case Staff = 'staff';
    case Transport = 'transport';
    case Medical = 'medical';
    case Marketing = 'marketing';
    case SponsorObligations = 'sponsor_obligations';
    case Contingency = 'contingency';

    // درآمد
    case Tickets = 'tickets';
    case Sponsorship = 'sponsorship';
    case MediaRights = 'media_rights';
    case Merchandise = 'merchandise';
    case OtherRevenue = 'other_revenue';

    public function label(): string
    {
        return match ($this) {
            self::Venue => 'محل',
            self::Equipment => 'تجهیزات',
            self::Staff => 'نیروی انسانی',
            self::Transport => 'حمل‌ونقل',
            self::Medical => 'پزشکی',
            self::Marketing => 'بازاریابی',
            self::SponsorObligations => 'تعهدات اسپانسر',
            self::Contingency => 'متفرقه / احتیاط',
            self::Tickets => 'بلیت / ثبت‌نام',
            self::Sponsorship => 'اسپانسر',
            self::MediaRights => 'حقوق رسانه',
            self::Merchandise => 'کالا',
            self::OtherRevenue => 'سایر درآمدها',
        };
    }

    public function type(): BudgetLineType
    {
        return match ($this) {
            self::Tickets,
            self::Sponsorship,
            self::MediaRights,
            self::Merchandise,
            self::OtherRevenue => BudgetLineType::Revenue,
            default => BudgetLineType::Expense,
        };
    }

    /** @return list<self> */
    public static function forType(BudgetLineType $type): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $c): bool => $c->type() === $type,
        ));
    }
}
