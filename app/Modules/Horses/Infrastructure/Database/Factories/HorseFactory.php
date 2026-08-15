<?php

declare(strict_types=1);

namespace App\Modules\Horses\Infrastructure\Database\Factories;

use App\Modules\Horses\Domain\Enums\HorseSex;
use App\Modules\Horses\Domain\Enums\HorseStatus;
use App\Modules\Horses\Domain\Models\Horse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horse>
 */
class HorseFactory extends Factory
{
    protected $model = Horse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Relámpago', 'Tornado', 'Lucero', 'Azabache', 'Centella', 'Guajiro']).' '.fake()->numberBetween(1, 99),
            'status' => HorseStatus::Active,
            'breed' => fake()->randomElement(['Cuarto de Milla', 'Criollo Colombiano', 'Paso Fino']),
            'color' => fake()->randomElement(['Alazán', 'Zaino', 'Tordillo', 'Bayo']),
            'sex' => fake()->randomElement(HorseSex::cases()),
            'birth_date' => fake()->dateTimeBetween('-15 years', '-4 years')->format('Y-m-d'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => HorseStatus::Inactive]);
    }
}
