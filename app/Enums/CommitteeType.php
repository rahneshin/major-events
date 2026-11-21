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
     *
     * @return list<self>
     */
    public static function templateFor(EventType $eventType): array
    {
        return match ($eventType) {
            EventType::Mega, EventType::MultiSport, EventType::Tournament, EventType::League => [
                self::Executive, self::Technical, self::Medical, self::Logistics, self::Security,
            ],
            EventType::Para => [
                self::Executive, self::Technical, self::Medical, self::Volunteers,
            ],
            EventType::Festival, EventType::Community => [
                self::Executive, self::Marketing, self::Volunteers,
            ],
            EventType::Youth => [
                self::Executive, self::Technical, self::Medical,
            ],
            EventType::Regional, EventType::Local, EventType::Other => [
                self::Executive, self::Technical,
            ],
        };
    }
}
