<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Application\Services\SaveCompetitionGroup;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de demostración: un torneo con tres grupos de salida, cuatro
 * categorías y veinte vaqueros, corrido hasta la mitad del primer día para
 * ver la consola de competencia y la clasificación en acción.
 */
class RiendaDemoSeeder extends Seeder
{
    /**
     * @var list<array{string, string, string, string}> nombre, apellido, ciudad, caballo
     */
    private const JINETES = [
        ['Carlos', 'Pérez', 'Montería', 'Relámpago'],
        ['Juan', 'Rodríguez', 'Sincelejo', 'Tornado'],
        ['Pedro', 'López', 'Valledupar', 'Lucero'],
        ['Andrés', 'Martínez', 'Montería', 'Azabache'],
        ['Luis', 'Gómez', 'Barranquilla', 'Centella'],
        ['Diego', 'Torres', 'Cartagena', 'Guajiro'],
        ['Miguel', 'Castro', 'Sincelejo', 'Cimarrón'],
        ['José', 'Ramírez', 'Montería', 'Canela'],
        ['Camilo', 'Hernández', 'Valledupar', 'Tormenta'],
        ['Santiago', 'Mendoza', 'Barranquilla', 'Zafiro'],
        ['Daniel', 'Ospina', 'Medellín', 'Brillante'],
        ['Andrés Felipe', 'Rojas', 'Bogotá', 'Cacique'],
        ['Sebastián', 'Herrera', 'Santa Marta', 'Galán'],
        ['Julián', 'Pineda', 'Montería', 'Halcón'],
        ['Óscar', 'Suárez', 'Cartagena', 'Jaguar'],
        ['Ricardo', 'Núñez', 'Sincelejo', 'Lancero'],
        ['Gabriel', 'Cárdenas', 'Valledupar', 'Moro'],
        ['Fabián', 'Moreno', 'Barranquilla', 'Pinto'],
        ['Esteban', 'Delgado', 'Montería', 'Rayado'],
        ['David', 'Fuentes', 'Santa Marta', 'Ventarrón'],
    ];

    /**
     * @var list<array{string, int}> nombre, orden
     */
    private const GRUPOS = [
        ['Apertura', 1],
        ['Centro', 2],
        ['Cierre', 3],
    ];

    /**
     * @var list<array{string, string, int}> nombre, descripción, orden
     */
    private const CATEGORIAS = [
        ['Profesional', 'Categoría principal del torneo.', 1],
        ['Aficionados', 'Vaqueros de nivel medio.', 2],
        ['Juvenil', 'Jóvenes promesas del lazo.', 3],
        ['Novatos', 'Primeros pasos en la competencia.', 4],
    ];

    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@rienda.test'],
            ['name' => 'Administrador RIENDA', 'password' => bcrypt('password')],
        );

        Auth::login($admin);

        $tournament = $this->createTournament();
        $groups = $this->createGroups($tournament);
        $categories = $this->createCategories($tournament);
        $participants = $this->createParticipants();
        $registrations = $this->register($categories, $participants, $groups);

        $mainCategory = $categories->firstWhere('order', 1);
        $this->runFirstDay(
            $mainCategory,
            $registrations->where('tournament_category_id', $mainCategory->id)
        );

        Auth::logout();

        $this->command->info('Administrador de prueba: admin@rienda.test / password');
    }

    /**
     * @return Collection<int, Participant>
     */
    private function createParticipants(): Collection
    {
        return collect(self::JINETES)->map(function (array $data): Participant {
            [$firstName, $lastName, $city, $horseName] = $data;

            $participant = Participant::query()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'city' => $city,
                'country' => 'Colombia',
                'document' => (string) fake()->unique()->numberBetween(1_000_000_000, 1_999_999_999),
                'status' => 'active',
            ]);

            Horse::query()->create([
                'participant_id' => $participant->id,
                'name' => $horseName,
                'owner' => $participant->full_name,
                'status' => 'active',
            ]);

            return $participant;
        });
    }

    private function createTournament(): Tournament
    {
        return Tournament::query()->create([
            'name' => 'Torneo Nacional de Lazo 2026',
            'slug' => 'torneo-nacional-de-lazo-2026',
            'description' => 'Competencia nacional de vaqueros de lazo.',
            'location' => 'Montería, Córdoba',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => TournamentStatus::InProgress,
        ]);
    }

    /**
     * @return Collection<int, CompetitionGroup>
     */
    private function createGroups(Tournament $tournament): Collection
    {
        $save = app(SaveCompetitionGroup::class);

        return collect(self::GRUPOS)->map(fn (array $data): CompetitionGroup => $save->create(
            $tournament,
            ['name' => $data[0], 'order' => $data[1]],
        ));
    }

    /**
     * @return Collection<int, TournamentCategory>
     */
    private function createCategories(Tournament $tournament): Collection
    {
        return collect(self::CATEGORIAS)->map(function (array $data) use ($tournament): TournamentCategory {
            [$name, $description, $order] = $data;

            return app(SaveTournamentCategory::class)->create($tournament, [
                'name' => $name,
                'description' => $description,
                'order' => $order,
                'number_of_days' => 3,
                'rounds_per_day' => 4,
            ]);
        });
    }

    /**
     * Reparte los vaqueros entre las categorías — cinco por categoría — y les
     * asigna los grupos de salida en orden circular.
     *
     * @param  Collection<int, TournamentCategory>  $categories
     * @param  Collection<int, Participant>  $participants
     * @param  Collection<int, CompetitionGroup>  $groups
     * @return Collection<int, Registration>
     */
    private function register($categories, $participants, $groups): Collection
    {
        $register = app(RegisterParticipantInCategory::class);
        $perCategory = (int) ceil($participants->count() / $categories->count());

        return $categories->values()->flatMap(
            fn (TournamentCategory $category, int $categoryIndex) => $participants
                ->slice($categoryIndex * $perCategory, $perCategory)
                ->values()
                ->map(
                    fn (Participant $participant, int $index) => $register->handle(
                        $category,
                        $participant,
                        $participant->horses()->first(),
                        group: $groups->get($index % $groups->count()),
                    )
                )
        );
    }

    /**
     * Corre el día 1 de la categoría principal con tiempos verosímiles y
     * alguna ronda fallida.
     *
     * @param  Collection<int, Registration>  $registrations
     */
    private function runFirstDay(TournamentCategory $category, $registrations): void
    {
        $lifecycle = app(ManageRoundLifecycle::class);
        $registerResult = app(RegisterRoundResult::class);

        $day = CompetitionDay::query()
            ->where('tournament_category_id', $category->id)
            ->where('day_number', 1)
            ->firstOrFail();

        $lifecycle->startDay($day);

        $rounds = Round::query()
            ->where('competition_day_id', $day->id)
            ->orderBy('round_number')
            ->get();

        // Las tres primeras rondas cierran solas al registrar el último
        // vaquero. En la cuarta, el último queda por salir: así la consola
        // abre con una ronda activa en la cola.
        foreach ($rounds as $index => $round) {
            $lifecycle->startRound($round);

            $riders = $index < 3
                ? $registrations
                : $registrations->take($registrations->count() - 1);

            foreach ($riders as $registration) {
                // Aproximadamente una de cada ocho rondas se falla.
                $failed = random_int(1, 8) === 1;

                $registerResult->handle(
                    $round->refresh(),
                    $registration,
                    RoundResultData::fromValidated([
                        'time' => sprintf('%d.%03d', random_int(7, 12), random_int(0, 999)),
                        'successful' => ! $failed,
                    ])
                );
            }
        }
    }
}
