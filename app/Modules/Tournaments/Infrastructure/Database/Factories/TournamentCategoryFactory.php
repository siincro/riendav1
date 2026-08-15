<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Infrastructure\Database\Factories;

use App\Modules\Tournaments\Domain\Enums\CategoryStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TournamentCategory>
 */
class TournamentCategoryFactory extends Factory
{
    protected $model = TournamentCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Profesional', 'Amateur', 'Juvenil', 'Femenina', 'Máster', 'Infantil']);

        return [
            'tournament_id' => Tournament::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'order' => fake()->numberBetween(0, 10),
            'number_of_days' => 3,
            'rounds_per_day' => 4,
            'status' => CategoryStatus::Pending,
        ];
    }

    public function structure(int $days, int $roundsPerDay): static
    {
        return $this->state(fn (): array => [
            'number_of_days' => $days,
            'rounds_per_day' => $roundsPerDay,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => CategoryStatus::InProgress]);
    }

    public function finished(): static
    {
        return $this->state(fn (): array => ['status' => CategoryStatus::Finished]);
    }
}
