<?php

declare(strict_types=1);

namespace App\Modules\Competition\Infrastructure\Database\Factories;

use App\Modules\Competition\Domain\Enums\CompetitionDayStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionDay>
 */
class CompetitionDayFactory extends Factory
{
    protected $model = CompetitionDay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_category_id' => TournamentCategory::factory(),
            'day_number' => 1,
            'date' => now()->toDateString(),
            'status' => CompetitionDayStatus::Pending,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => CompetitionDayStatus::Active]);
    }
}
