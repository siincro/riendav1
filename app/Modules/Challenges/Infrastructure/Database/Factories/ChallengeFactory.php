<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Infrastructure\Database\Factories;

use App\Modules\Challenges\Domain\Enums\ChallengeStatus;
use App\Modules\Challenges\Domain\Models\Challenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ChallengeStatus::Open,
        ];
    }

    public function settled(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeStatus::Settled,
            'settled_at' => now(),
        ]);
    }
}
