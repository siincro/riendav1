<?php

declare(strict_types=1);

namespace App\Modules\Registrations\Infrastructure\Database\Factories;

use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Domain\Enums\RegistrationStatus;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_category_id' => TournamentCategory::factory(),
            'participant_id' => Participant::factory(),
            'horse_id' => null,
            'registration_number' => fake()->unique()->numberBetween(1, 5000),
            'status' => RegistrationStatus::Active,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (): array => ['status' => RegistrationStatus::Withdrawn]);
    }
}
