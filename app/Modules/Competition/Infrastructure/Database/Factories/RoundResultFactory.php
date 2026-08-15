<?php

declare(strict_types=1);

namespace App\Modules\Competition\Infrastructure\Database\Factories;

use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Registrations\Domain\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoundResult>
 */
class RoundResultFactory extends Factory
{
    protected $model = RoundResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'registration_id' => Registration::factory(),
            'time_ms' => fake()->numberBetween(6_000, 15_000),
            'successful' => true,
        ];
    }

    /**
     * Ronda fallida: sin tiempo acumulable.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'successful' => false,
            'time_ms' => 0,
        ]);
    }

    public function withTime(int $milliseconds): static
    {
        return $this->state(fn (): array => [
            'successful' => true,
            'time_ms' => $milliseconds,
        ]);
    }
}
