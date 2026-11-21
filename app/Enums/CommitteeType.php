<?php

declare(strict_types=1);

namespace App\Enums;

enum CommitteeType: string
{
    case Executive = 'executive';
    case Finance = 'finance';
    case Marketing = 'marketing';
    case Technical = 'technical';
    case Medical = 'medical';
    case Logistics = 'logistics';
    case Volunteers = 'volunteers';
    case Security = 'security';
    case Protocol = 'protocol';
    case Custom = 'custom';

    public function label(): string
    {
        return __('committee.types.'.$this->value);
    }

    public function description(): string
    {
        return $this === self::Custom ? '' : __('committee.type_descriptions.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Executive => '#6366f1',
            self::Finance => '#0ea5e9',
            self::Marketing => '#ec4899',
            self::Technical => '#f59e0b',
            self::Medical => '#ef4444',
            self::Logistics => '#14b8a6',
            self::Volunteers => '#8b5cf6',
            self::Security => '#64748b',
            self::Protocol => '#a855f7',
            self::Custom => '#94a3b8',
        };
    }

    /**
     * Suggested committee set per event type (deterministic; AI enriches later).
     * Finance is part of every template because budget (Step 09) is committee-aware.
     *
     * @return list<self>
     */
    public static function templateFor(?EventType $eventType): array
    {
        $core = [self::Executive, self::Finance, self::Technical, self::Logistics];

        return match ($eventType) {
            EventType::Mega, EventType::MultiSport => [
                self::Executive, self::Finance, self::Marketing, self::Technical,
                self::Medical, self::Logistics, self::Volunteers, self::Security, self::Protocol,
            ],
            EventType::Regional => [
                self::Executive, self::Finance, self::Marketing, self::Technical,
                self::Medical, self::Logistics, self::Volunteers,
            ],
            EventType::Tournament, EventType::League => [...$core, self::Medical, self::Security],
            EventType::Youth, EventType::Para => [...$core, self::Medical, self::Volunteers],
            EventType::Festival, EventType::Community => [
                self::Executive, self::Finance, self::Marketing, self::Volunteers, self::Logistics,
            ],
            EventType::Local => $core,
            default => [self::Executive, self::Finance, self::Logistics],
        };
    }
}
