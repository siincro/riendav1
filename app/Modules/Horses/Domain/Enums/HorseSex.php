<?php

declare(strict_types=1);

namespace App\Modules\Horses\Domain\Enums;

enum HorseSex: string
{
    case Male = 'male';
    case Female = 'female';
    case Gelding = 'gelding';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Macho',
            self::Female => 'Hembra',
            self::Gelding => 'Castrado',
        };
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
