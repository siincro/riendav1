<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Infrastructure\Database\Factories;

use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Torneo '.fake()->unique()->city();
        $start = fake()->dateTimeBetween('-1 year', '+6 months');

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'location' => fake()->city().', Colombia',
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+2 days')->format('Y-m-d'),
            'status' => TournamentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => TournamentStatus::Published]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => TournamentStatus::InProgress]);
    }

    public function finished(): static
    {
        return $this->state(fn (): array => ['status' => TournamentStatus::Finished]);
    }
}
