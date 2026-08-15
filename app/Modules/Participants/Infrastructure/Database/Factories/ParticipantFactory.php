<?php

declare(strict_types=1);

namespace App\Modules\Participants\Infrastructure\Database\Factories;

use App\Modules\Participants\Domain\Enums\ParticipantStatus;
use App\Modules\Participants\Domain\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document' => fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('3#########'),
            'city' => fake()->city(),
            'country' => 'Colombia',
            'status' => ParticipantStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ParticipantStatus::Inactive]);
    }
}
