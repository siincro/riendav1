<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Enums;

enum CompetitionDayStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Active => 'En curso',
            self::Finished => 'Finalizado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Finished, self::Cancelled], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
