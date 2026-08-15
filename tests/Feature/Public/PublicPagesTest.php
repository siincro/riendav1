<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La cara pública de RIENDA: landing, torneos, clasificaciones y perfiles.
 *
 * Toda la superficie pública es de solo lectura y no expone ids internos ni
 * datos sensibles de los jinetes.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------- landing

    #[Test]
    public function la_landing_muestra_el_torneo_en_vivo_con_su_clasificacion(): void
    {
        [$tournament, $category, $registrations] = $this->liveScenario();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Home')
                ->where('active.tournament.slug', $tournament->slug)
                ->has('active.boards', 1)
                ->where('active.boards.0.category.name', $category->name)
                ->where('active.boards.0.day.day_number', 1)
                ->where('active.boards.0.round.round_number', 2)
                ->has('active.boards.0.standings.data', 3)
                ->where('active.boards.0.standings.data.0.position', 1)
                ->where('active.boards.0.standings.data.0.registration.participant.full_name', $registrations[0]->participant->full_name)
                ->where('active.boards.0.standings.data.2.position', 3)
            );
    }

    #[Test]
    public function la_landing_trae_un_marcador_por_cada_categoria_en_pista(): void
    {
        [$tournament, $category] = $this->liveScenario();

        // Una segunda categoría también en competencia…
        $segunda = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Damas',
            // El orden de las categorías manda en el de los marcadores.
            'order' => 1,
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->registerWithHorse($segunda, 'Ana', 'Gómez');
        $this->startRound($segunda, 1, 1);

        // …y una tercera que todavía no arranca: viaja como etiqueta, sin marcador.
        $tercera = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Infantil',
            'order' => 2,
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Home')
                ->has('active.categories', 3)
                ->has('active.boards', 2)
                ->where('active.boards.0.category.name', $category->name)
                ->where('active.boards.1.category.name', $segunda->name)
                ->where('active.boards.1.round.round_number', 1)
                ->whereNot('active.boards.0.category.name', $tercera->name)
                ->whereNot('active.boards.1.category.name', $tercera->name)
            );
    }

    #[Test]
    public function la_landing_lista_los_proximos_torneos_y_oculta_los_borradores(): void
    {
        $published = Tournament::factory()->published()->create([
            'name' => 'Copa Colombia',
            'slug' => 'copa-colombia',
        ]);
        Tournament::factory()->create(['name' => 'Privado', 'slug' => 'privado']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Home')
                ->has('upcoming.data', 1)
                ->where('upcoming.data.0.name', $published->name)
            );
    }

    #[Test]
    public function la_landing_muestra_ultimos_resultados_y_jinetes_destacados(): void
    {
        $this->finishedScenario();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Home')
                ->where('featured.0.full_name', 'Ana Gómez')
                ->where('featured.0.wins_count', 1)
                ->where('latest_results.0.participant.full_name', 'Ana Gómez')
                ->where('latest_results.0.tournament.name', 'Copa Colombia')
                ->where('latest_results.0.round_number', 2)
            );
    }

    // ------------------------------------------------------------ torneos

    #[Test]
    public function el_directorio_de_torneos_solo_muestra_los_visibles(): void
    {
        Tournament::factory()->published()->create(['name' => 'A Publicado', 'slug' => 'a-publicado']);
        Tournament::factory()->inProgress()->create(['name' => 'B En Vivo', 'slug' => 'b-en-vivo']);
        Tournament::factory()->finished()->create(['name' => 'C Finalizado', 'slug' => 'c-finalizado']);
        Tournament::factory()->create(['name' => 'D Borrador', 'slug' => 'd-borrador']);

        $this->get(route('public.tournaments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tournaments/Index')
                ->has('tournaments.data', 3)
                ->has('tournaments.data', 3, fn (Assert $page) => $page
                    ->has('name')
                    ->has('public_id')
                    ->whereNot('name', 'D Borrador')
                    ->etc()
                )
            );
    }

    #[Test]
    public function el_directorio_de_torneos_busca_por_nombre(): void
    {
        Tournament::factory()->published()->create(['name' => 'Copa Montería', 'slug' => 'copa-monteria']);
        Tournament::factory()->published()->create(['name' => 'Encuentro de Valledupar', 'slug' => 'encuentro-valledupar']);

        $this->get(route('public.tournaments.index', ['search' => 'montería']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tournaments/Index')
                ->has('tournaments.data', 1)
                ->where('tournaments.data.0.name', 'Copa Montería')
                ->where('filters.search', 'montería')
            );
    }

    #[Test]
    public function el_detalle_de_un_torneo_muestra_sus_categorias(): void
    {
        [$tournament, $category] = $this->tournamentWithCategory();
        TournamentCategory::factory()->for($tournament)->create(['name' => 'Juvenil', 'slug' => 'juvenil']);

        $this->get(route('public.tournaments.show', $tournament))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tournaments/Show')
                ->where('tournament.slug', $tournament->slug)
                ->has('tournament.categories', 2)
            );
    }

    #[Test]
    public function un_torneo_en_borrador_no_tiene_detalle_publico(): void
    {
        $draft = Tournament::factory()->create();

        $this->get(route('public.tournaments.show', $draft))->assertNotFound();
    }

    // ---------------------------------------------------- clasificaciones

    #[Test]
    public function la_clasificacion_publica_muestra_posiciones_dias_y_detalle_diario(): void
    {
        [$tournament, $category, $registrations] = $this->finishedScenario();

        $this->get(route('public.tournaments.categories.show', [
            'tournament' => $tournament->slug,
            'category' => $category->slug,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tournaments/Category')
                ->where('category.name', $category->name)
                ->has('standings.data', 2)
                ->where('standings.data.0.position', 1)
                ->where('standings.data.0.registration.participant.full_name', 'Ana Gómez')
                ->where('standings.data.1.position', 2)
                ->has('days', 1)
                ->where('days.0.day_number', 1)
                ->has('days.0.rounds', 3)
                ->has('rows', 2)
                ->where('rows.0.participant.full_name', 'Luis Mora')
                ->where('rows.0.per_day.0.successful_rounds', 2)
                ->where('rows.1.per_day.0.time_formatted', '15.500')
            );
    }

    #[Test]
    public function la_clasificacion_de_un_torneo_en_borrador_devuelve_404(): void
    {
        [$tournament, $category] = $this->tournamentWithCategory();

        $tournament->status = TournamentStatus::Draft;
        $tournament->save();

        $this->get(route('public.tournaments.categories.show', [
            'tournament' => $tournament->slug,
            'category' => $category->slug,
        ]))->assertNotFound();
    }

    // --------------------------------------------------------- jinetes

    #[Test]
    public function el_directorio_de_jinetes_solo_muestra_activos(): void
    {
        Participant::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Pérez']);
        Participant::factory()->create(['first_name' => 'Ana', 'last_name' => 'Gómez']);
        Participant::factory()->inactive()->create(['first_name' => 'Retirado', 'last_name' => 'Ruiz']);

        $this->get(route('public.participants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/participants/Index')
                ->has('participants.data', 2)
                ->whereNot('participants.data.0.full_name', 'Retirado Ruiz')
            );
    }

    #[Test]
    public function el_directorio_de_jinetes_no_busca_por_documento(): void
    {
        Participant::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Pérez', 'document' => '1098765432']);

        $this->get(route('public.participants.index', ['search' => 'Pérez']))
            ->assertInertia(fn (Assert $page) => $page->has('participants.data', 1));

        // El documento es un dato interno: no se puede usar para buscar.
        $this->get(route('public.participants.index', ['search' => '1098765432']))
            ->assertInertia(fn (Assert $page) => $page->has('participants.data', 0));
    }

    #[Test]
    public function el_perfil_publico_muestra_caballos_e_historial_deportivo(): void
    {
        [$tournament, $category, $registrations] = $this->finishedScenario();
        $ana = $registrations[0]->participant;

        Horse::factory()->for($ana)->create(['name' => 'Relámpago']);
        Horse::factory()->for($ana)->create(['name' => 'Tornado']);

        $this->get(route('public.participants.show', $ana))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/participants/Show')
                ->where('participant.full_name', 'Ana Gómez')
                ->has('horses', 3)
                ->has('history', 1)
                ->where('history.0.tournament.name', $tournament->name)
                ->where('history.0.standing.position', 1)
                ->where('history.0.category.name', $category->name)
            );
    }

    #[Test]
    public function el_historial_no_mezcla_torneos_en_borrador(): void
    {
        [$tournament, $category, $registrations] = $this->finishedScenario();
        $ana = $registrations[0]->participant;

        // Un torneo publicado donde también compitió, pasado después a
        // borrador: no debe aparecer en su perfil público.
        $draft = Tournament::factory()->published()->create(['name' => 'Privado', 'slug' => 'privado']);
        $draftCategory = app(SaveTournamentCategory::class)->create($draft, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);
        $draftRegistration = app(RegisterParticipantInCategory::class)->handle($draftCategory, $ana);
        $this->record($draftCategory, 1, 1, $draftRegistration, '8.000');

        $draft->status = TournamentStatus::Draft;
        $draft->save();

        $this->get(route('public.participants.show', $ana))
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/participants/Show')
                ->has('history', 1)
                ->whereNot('history.0.tournament.slug', $draft->slug)
            );
    }

    #[Test]
    public function un_jinete_inactivo_no_tiene_perfil_publico(): void
    {
        $inactive = Participant::factory()->inactive()->create();

        $this->get(route('public.participants.show', $inactive))->assertNotFound();
    }

    // ------------------------------------------------------------- helpers

    /**
     * @return array{Tournament, TournamentCategory, list<Registration>}
     */
    private function liveScenario(): array
    {
        $tournament = Tournament::factory()->published()->create([
            'name' => 'Torneo Nacional de Lazo',
            'slug' => 'torneo-nacional-de-lazo',
            'location' => 'Montería, Colombia',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-22',
        ]);

        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $registrations = [
            $this->registerWithHorse($category, 'Carlos', 'Pérez'),
            $this->registerWithHorse($category, 'Juan', 'Rodríguez'),
            $this->registerWithHorse($category, 'Pedro', 'López'),
        ];

        $this->record($category, 1, 1, $registrations[0], '8.000');
        $this->record($category, 1, 1, $registrations[1], '8.500');
        $this->record($category, 1, 1, $registrations[2], '9.000');

        // La ronda 1 se cierra y la ronda 2 queda abierta: el bloque "en vivo"
        // reporta la ronda 2 como la actual.
        app(ManageRoundLifecycle::class)->completeRound($this->round($category, 1, 1));
        $this->startRound($category, 1, 2);

        return [$tournament, $category, $registrations];
    }

    /**
     * @return array{Tournament, TournamentCategory, list<Registration>}
     */
    private function finishedScenario(): array
    {
        $tournament = Tournament::factory()->published()->create([
            'name' => 'Copa Colombia',
            'slug' => 'copa-colombia',
        ]);

        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $luis = $this->registerWithHorse($category, 'Luis', 'Mora');
        $ana = $this->registerWithHorse($category, 'Ana', 'Gómez');

        $this->record($category, 1, 1, $luis, '8.500');
        $this->record($category, 1, 1, $ana, '8.000');
        $this->record($category, 1, 2, $luis, '7.800');
        $this->record($category, 1, 2, $ana, '7.500');

        app(ManageRoundLifecycle::class)->completeCategory($category->refresh());

        $tournament->status = TournamentStatus::Finished;
        $tournament->save();

        return [$tournament, $category, [$ana, $luis]];
    }

    /**
     * @return array{Tournament, TournamentCategory}
     */
    private function tournamentWithCategory(): array
    {
        $tournament = Tournament::factory()->published()->create();

        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        return [$tournament, $category];
    }

    private function registerWithHorse(TournamentCategory $category, string $firstName, string $lastName): Registration
    {
        $participant = Participant::factory()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $horse = Horse::factory()->for($participant)->create();

        return app(RegisterParticipantInCategory::class)->handle($category, $participant, $horse);
    }

    private function record(
        TournamentCategory $category,
        int $dayNumber,
        int $roundNumber,
        Registration $registration,
        string $seconds
    ): void {
        $round = $this->round($category, $dayNumber, $roundNumber);

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['time' => $seconds, 'successful' => true])
        );
    }

    private function startRound(TournamentCategory $category, int $dayNumber, int $roundNumber): void
    {
        app(ManageRoundLifecycle::class)->startRound($this->round($category, $dayNumber, $roundNumber));
    }

    private function round(TournamentCategory $category, int $dayNumber, int $roundNumber): Round
    {
        return Round::query()
            ->whereIn(
                'competition_day_id',
                CompetitionDay::query()
                    ->where('tournament_category_id', $category->id)
                    ->where('day_number', $dayNumber)
                    ->select('id')
            )
            ->where('round_number', $roundNumber)
            ->firstOrFail();
    }
}
