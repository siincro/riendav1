<?php

declare(strict_types=1);

namespace App\Modules\Competition\Infrastructure\Database\Factories;

use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionGroup>
 */
class CompetitionGroupFactory extends Factory
{
    protected $model = CompetitionGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'name' => 'Grupo '.fake()->unique()->bothify('##'),
            'order' => 0,
        ];
    }
}
