<?php

declare(strict_types=1);

namespace App\Modules\Competition\Infrastructure\Database\Factories;

use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    protected $model = Round::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_day_id' => CompetitionDay::factory(),
            'round_number' => 1,
            'status' => RoundStatus::Pending,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => RoundStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (): array => [
            'status' => RoundStatus::Finished,
            'started_at' => now()->subMinutes(20),
            'finished_at' => now(),
        ]);
    }
}
