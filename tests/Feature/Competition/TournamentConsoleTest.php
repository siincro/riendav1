<?php

declare(strict_types=1);

namespace Tests\Feature\Competition;

use App\Models\User;
use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\BuildTournamentRunOrder;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Application\Services\SaveCompetitionGroup;
use App\Modules\Competition\Domain\Enums\CompetitionDayStatus;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Enums\CategoryStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La consola del evento y sus piezas: grupos de salida, cola automática y
 * captura de tiempos sin cambiar de categoría.
 */
class TournamentConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    // --------------------------------------------------------- grupos de salida

    #[Test]
    public function el_administrador_crea_un_grupo_de_salida_en_el_torneo(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->post("/admin/tournaments/{$tournament->slug}/groups", [
                'name' => 'Apertura',
                'order' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('competition_groups', [
            'tournament_id' => $tournament->id,
            'name' => 'Apertura',
            'order' => 1,
        ]);
    }

    #[Test]
    public function el_nombre_del_grupo_es_unico_dentro_del_torneo(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Apertura', 'order' => 1]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->post("/admin/tournaments/{$tournament->slug}/groups", [
                'name' => 'Apertura',
                'order' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $tournament->competitionGroups()->count());
    }

    #[Test]
    public function el_mismo_nombre_de_grupo_es_valido_en_otro_torneo(): void
    {
        $primero = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $segundo = Tournament::factory()->published()->create(['slug' => 'copa-antioquia']);
        app(SaveCompetitionGroup::class)->create($primero, ['name' => 'Apertura', 'order' => 1]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$segundo->slug}")
            ->post("/admin/tournaments/{$segundo->slug}/groups", [
                'name' => 'Apertura',
                'order' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $segundo->competitionGroups()->count());
    }

    #[Test]
    public function editar_un_grupo_actualiza_nombre_y_orden(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $group = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Apertura', 'order' => 1]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->put("/admin/tournaments/{$tournament->slug}/groups/{$group->id}", [
                'name' => 'Cierre',
                'order' => 9,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $group->refresh();
        $this->assertSame('Cierre', $group->name);
        $this->assertSame(9, $group->order);
    }

    #[Test]
    public function borrar_un_grupo_conserva_el_historial_de_las_inscripciones(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);
        $group = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Apertura', 'order' => 1]);

        $registration = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
            group: $group,
        );
        $this->assertSame($group->id, $registration->competition_group_id);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->delete("/admin/tournaments/{$tournament->slug}/groups/{$group->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('competition_groups', ['id' => $group->id]);

        // La inscripción sobrevive, sin asignación operativa.
        $this->assertNull($registration->refresh()->competition_group_id);
    }

    #[Test]
    public function la_ficha_del_torneo_expone_los_grupos_con_su_conteo(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);
        $group = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Apertura', 'order' => 1]);
        app(RegisterParticipantInCategory::class)->handle($category, Participant::factory()->create(), group: $group);

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tournaments/Show')
                ->where('tournament.competition_groups.0.id', $group->id)
                ->where('tournament.competition_groups.0.name', $group->name)
                ->where('tournament.competition_groups.0.registrations_count', 1)
            );
    }

    // ------------------------------------------------------------ cola de salida

    #[Test]
    public function la_cola_del_dia_contiene_la_siguiente_ronda_pendiente_de_cada_inscripcion(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 2);

        $registrado = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );
        $pendiente = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        // Quien ya registró en la ronda activa sale de la cola hasta que la
        // ronda cierre y todos pasen a la siguiente.
        $this->startRound($category, 1);
        $this->record($category, $registrado, 1, '8.342');

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];

        $this->assertCount(1, $queue);
        $this->assertSame($pendiente->id, $queue[0]['registration_id']);
        $this->assertSame(1, $queue[0]['round']['round_number']);

        // Al cerrar el lazo, ambos quedan pendientes del segundo.
        app(ManageRoundLifecycle::class)->completeRound($this->round($category, 1));

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];

        $this->assertCount(2, $queue);
        $this->assertSame(2, $queue[0]['round']['round_number']);
        $this->assertSame(2, $queue[1]['round']['round_number']);
    }

    #[Test]
    public function la_cola_respeta_el_orden_del_grupo_y_luego_el_dorsal(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);
        $grupoA = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Apertura', 'order' => 1]);
        $grupoB = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Cierre', 'order' => 2]);

        // Se inscriben en orden inverso: el dorsal bajo queda en el grupo de
        // orden alto, y el dorsal alto en el grupo de orden bajo.
        $enGrupoB = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
            group: $grupoB,
        );
        $enGrupoA = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
            group: $grupoA,
        );
        $sinGrupo = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];

        // El orden es por grupo primero y dorsal después; el no asignado a
        // ningún grupo sale al final, tras los grupos definidos.
        $this->assertSame(
            [$enGrupoA->id, $enGrupoB->id, $sinGrupo->id],
            array_column($queue, 'registration_id')
        );
        $this->assertSame([1, 2, 3], array_column($queue, 'sequence'));
    }

    #[Test]
    public function la_cola_prioriza_la_ronda_activa_sobre_el_orden_del_grupo(): void
    {
        $tournament = $this->tournament();
        $activa = $this->category($tournament, 'En pista', days: 1, rounds: 1);
        $pendiente = $this->category($tournament, 'Espera', days: 1, rounds: 1);
        $grupoPendiente = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Apertura', 'order' => 1]);
        $grupoActiva = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Cierre', 'order' => 2]);

        $enPendiente = app(RegisterParticipantInCategory::class)->handle(
            $pendiente,
            Participant::factory()->create(),
            group: $grupoPendiente,
        );
        $enActiva = app(RegisterParticipantInCategory::class)->handle(
            $activa,
            Participant::factory()->create(),
            group: $grupoActiva,
        );

        // La categoría «En pista» arrancó su lazo: quien corre en una ronda
        // activa sale antes que el que espera en una pendiente, aun con el
        // grupo de orden menor.
        app(ManageRoundLifecycle::class)->startRound($this->round($activa, 1));

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];

        $this->assertCount(2, $queue);
        $this->assertSame($enActiva->id, $queue[0]['registration_id']);
        $this->assertSame($enPendiente->id, $queue[1]['registration_id']);
    }

    #[Test]
    public function el_lazo_se_cierra_solo_cuando_todos_registran_y_la_cola_avanza(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 2);

        $primero = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );
        $ultimo = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $this->startRound($category, 1);
        $this->record($category, $primero, 1, '8.342');

        // Con uno por salir el lazo sigue en curso.
        $this->assertSame(RoundStatus::Active, $this->round($category, 1)->status);

        // El último registro cierra el lazo y ambos quedan en la siguiente.
        $this->record($category, $ultimo, 1, '9.150');

        $this->assertSame(RoundStatus::Finished, $this->round($category, 1)->status);

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];
        $this->assertCount(2, $queue);
        $this->assertSame(2, $queue[0]['round']['round_number']);
        $this->assertSame(2, $queue[1]['round']['round_number']);
    }

    #[Test]
    public function el_lazo_sigue_abierto_mientras_haya_alguien_por_salir(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);

        $registrado = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );
        $porSalir = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $this->startRound($category, 1);
        $this->record($category, $registrado, 1, '8.342');

        // La ronda sigue activa: no se cierra con la mitad de la cola.
        $this->assertSame(RoundStatus::Active, $this->round($category, 1)->status);

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];
        $this->assertCount(1, $queue);
        $this->assertSame($porSalir->id, $queue[0]['registration_id']);
        $this->assertSame(1, $queue[0]['round']['round_number']);
    }

    #[Test]
    public function el_dia_de_trabajo_por_defecto_es_el_activo_y_luego_el_pendiente(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 2, rounds: 1);

        // Un inscrito habilita el arranque del día.
        app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $this->assertSame(
            1,
            app(BuildTournamentRunOrder::class)->handle($tournament)['selected_day_number'],
            'Sin nada activo, se toma el primer día pendiente.'
        );

        app(ManageRoundLifecycle::class)->startDay($this->day($category, 1));

        $tournament->refresh();

        $this->assertSame(
            1,
            app(BuildTournamentRunOrder::class)->handle($tournament)['selected_day_number'],
            'Con un día activo, se toma ese.'
        );

        app(ManageRoundLifecycle::class)->completeDay($this->day($category, 1));

        $tournament->refresh();

        $this->assertSame(
            2,
            app(BuildTournamentRunOrder::class)->handle($tournament)['selected_day_number'],
            'Finalizado el primero, el trabajo se mueve al segundo.'
        );
    }

    #[Test]
    public function la_estructura_crea_un_lazo_final_extra_en_el_ultimo_dia(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 2, rounds: 2);

        $primerDia = $this->day($category, 1);
        $ultimoDia = $this->day($category, 2);

        $this->assertSame(
            [1, 2],
            $this->roundNumbers($primerDia),
            'El primer día solo tiene las rondas regulares.'
        );
        $this->assertSame(
            [1, 2, 3],
            $this->roundNumbers($ultimoDia),
            'El último día suma la final como ronda extra.'
        );

        $this->assertTrue($this->roundNumber($ultimoDia, 3)->is_final);
        $this->assertFalse($this->roundNumber($ultimoDia, 1)->is_final);
        $this->assertFalse($this->roundNumber($ultimoDia, 2)->is_final);
    }

    #[Test]
    public function la_final_solo_la_corre_el_top_10_de_la_clasificacion(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);

        $inscritos = collect(range(1, 12))
            ->map(fn (int $i): Registration => app(RegisterParticipantInCategory::class)->handle(
                $category,
                Participant::factory()->create(),
            ));

        // Tiempos crecientes: quien primero se inscribió corre el mejor tiempo
        // y queda primero; los dos últimos se quedan fuera del top 10.
        $inscritos->each(function (Registration $registration, int $index) use ($category): void {
            $this->record($category, $registration, 1, sprintf('10.%03d', $index));
        });

        // Al registrar el último de la ronda 1, el lazo se cerró solo.
        $this->assertSame(RoundStatus::Finished, $this->round($category, 1)->status);

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];

        $this->assertCount(10, $queue, 'Solo los 10 primeros de la clasificación entran a la final.');

        $ids = array_column($queue, 'registration_id');
        $this->assertSame($inscritos->take(10)->pluck('id')->all(), $ids);
        $this->assertNotContains($inscritos[10]->id, $ids);
        $this->assertNotContains($inscritos[11]->id, $ids);

        $this->assertSame(2, $queue[0]['round']['round_number']);
        $this->assertTrue($queue[0]['round']['is_final']);
        $this->assertTrue($queue[9]['round']['is_final']);
    }

    #[Test]
    public function la_final_se_cierra_cuando_registran_todos_los_finalistas(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);

        $inscritos = collect(range(1, 12))
            ->map(fn (int $i): Registration => app(RegisterParticipantInCategory::class)->handle(
                $category,
                Participant::factory()->create(),
            ));

        // Todos corren la ronda regular y esta se cierra sola.
        $inscritos->each(function (Registration $registration, int $index) use ($category): void {
            $this->record($category, $registration, 1, sprintf('10.%03d', $index));
        });

        $final = $this->round($category, 2);
        $this->assertTrue($final->is_final);
        $this->assertSame(RoundStatus::Pending, $final->status);

        // El primer finalista en la final la deja en curso; con nueve de diez
        // registrados sigue abierta.
        $inscritos->take(9)->each(function (Registration $registration, int $index) use ($category): void {
            $this->record($category, $registration, 2, sprintf('9.5%02d', $index));
        });
        $this->assertSame(RoundStatus::Active, $this->round($category, 2)->status);

        // El décimo registro la cierra: el último finalista salió.
        $this->record($category, $inscritos[9], 2, '9.600');
        $this->assertSame(RoundStatus::Finished, $this->round($category, 2)->status);

        // La jornada terminó: la cola queda sin pendientes.
        $queue = app(BuildTournamentRunOrder::class)->handle($tournament)['queue'];
        $this->assertSame([], $queue);
    }

    // ------------------------------------------------------------ consola (HTTP)

    #[Test]
    public function la_consola_del_evento_muestra_la_entrada_actual(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);
        $registration = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}/competition")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('competition/TournamentConsole')
                ->where('tournament.slug', $tournament->slug)
                ->where('selectedDayNumber', 1)
                ->where('currentEntry.registration_id', $registration->id)
                ->where('currentEntry.round.accepts_results', false)
            );
    }

    #[Test]
    public function registrar_un_tiempo_desde_la_consola_arranca_el_lazo_y_guarda_el_resultado(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);
        $registration = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->post("/admin/tournaments/{$tournament->slug}/competition/results", [
                'registration_id' => $registration->id,
                'time' => '8.342',
                'successful' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $result = RoundResult::query()->where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame(8342, $result->time_ms);
        // El registro exitoso demuestra que el lazo arrancó; con un solo
        // inscrito, al registrar, la ronda se cierra sola.
        $this->assertSame(RoundStatus::Finished, $this->round($category, 1)->status);
    }

    #[Test]
    public function la_consola_central_inicia_el_dia_del_vaquero(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);
        app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->put("/admin/tournaments/{$tournament->slug}/competition/days/{$this->day($category, 1)->id}/start")
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(CompetitionDayStatus::Active, $this->day($category, 1)->status);
        $this->assertSame(CategoryStatus::InProgress, $category->refresh()->status);
    }

    #[Test]
    public function la_consola_central_inicia_el_lazo_del_vaquero(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);
        app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->put("/admin/tournaments/{$tournament->slug}/competition/rounds/{$this->round($category, 1)->id}/start")
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // Iniciar el lazo arranca también el día si estaba pendiente.
        $this->assertSame(RoundStatus::Active, $this->round($category, 1)->status);
        $this->assertSame(CompetitionDayStatus::Active, $this->day($category, 1)->status);
    }

    #[Test]
    public function la_consola_central_rechaza_iniciar_el_lazo_de_otro_torneo(): void
    {
        $tournament = $this->tournament();
        $this->category($tournament, days: 1, rounds: 1);

        $ajeno = $this->round(
            $this->category($this->tournament('otro-torneo'), 'Otro', days: 1, rounds: 1),
            1,
        );

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->put("/admin/tournaments/{$tournament->slug}/competition/rounds/{$ajeno->id}/start")
            ->assertRedirect()
            ->assertSessionHasErrors('domain');

        $this->assertSame(RoundStatus::Pending, $ajeno->refresh()->status);
    }

    #[Test]
    public function el_segundo_dia_ordena_por_menos_lazos_acertados_y_mas_tiempo(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 2, rounds: 2);
        $sinAcierto = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );
        $lento = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );
        $rapido = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        // Primer día: el lento y el rápido aciertan sus dos lazos; el otro falla.
        $this->record($category, $lento, 1, '90.000');
        $this->record($category, $lento, 2, '95.000');
        $this->record($category, $rapido, 1, '60.000');
        $this->record($category, $rapido, 2, '65.000');
        $this->recordFailed($category, $sinAcierto, 1);
        $this->recordFailed($category, $sinAcierto, 2);

        // Día 2: primero el que no acertó, luego el lento (más tiempo), al final el rápido.
        $queue = app(BuildTournamentRunOrder::class)->handle($tournament, 2)['queue'];

        $this->assertSame(
            [$sinAcierto->id, $lento->id, $rapido->id],
            array_column($queue, 'registration_id'),
        );
    }

    #[Test]
    public function el_primer_dia_mantiene_el_orden_por_grupos(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 2, rounds: 1);
        $grupoB = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Grupo B', 'order' => 2]);
        $grupoA = app(SaveCompetitionGroup::class)->create($tournament, ['name' => 'Grupo A', 'order' => 1]);
        $enB = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
            group: $grupoB,
        );
        $enA = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
            group: $grupoA,
        );
        $sinGrupo = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );

        $queue = app(BuildTournamentRunOrder::class)->handle($tournament, 1)['queue'];

        $this->assertSame(
            [$enA->id, $enB->id, $sinGrupo->id],
            array_column($queue, 'registration_id'),
        );
    }

    #[Test]
    public function la_consola_central_inicia_el_dia_de_todas_las_categorias(): void
    {
        $tournament = $this->tournament();
        $profesional = $this->category($tournament, 'Profesional', days: 2, rounds: 1);
        $novatos = $this->category($tournament, 'Novatos', days: 2, rounds: 1);
        app(RegisterParticipantInCategory::class)->handle(
            $profesional,
            Participant::factory()->create(),
        );
        app(RegisterParticipantInCategory::class)->handle(
            $novatos,
            Participant::factory()->create(),
        );

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->put("/admin/tournaments/{$tournament->slug}/competition/start-all-days", [
                'day' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(CompetitionDayStatus::Active, $this->day($profesional, 1)->status);
        $this->assertSame(CompetitionDayStatus::Active, $this->day($novatos, 1)->status);
    }

    #[Test]
    public function iniciar_todos_los_dias_omite_las_categorias_sin_vaqueros(): void
    {
        $tournament = $this->tournament();
        $profesional = $this->category($tournament, 'Profesional', days: 2, rounds: 1);
        $vacia = $this->category($tournament, 'Vacíos', days: 2, rounds: 1);
        app(RegisterParticipantInCategory::class)->handle(
            $profesional,
            Participant::factory()->create(),
        );

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->put("/admin/tournaments/{$tournament->slug}/competition/start-all-days", [
                'day' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(CompetitionDayStatus::Active, $this->day($profesional, 1)->status);
        $this->assertSame(CompetitionDayStatus::Pending, $this->day($vacia, 1)->status);
    }

    #[Test]
    public function la_consola_rechaza_una_inscripcion_de_otro_torneo(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);
        $ajeno = app(RegisterParticipantInCategory::class)->handle(
            $this->category($this->tournament('otro-torneo'), 'Otro', days: 1, rounds: 1),
            Participant::factory()->create(),
        );

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->post("/admin/tournaments/{$tournament->slug}/competition/results", [
                'registration_id' => $ajeno->id,
                'time' => '8.342',
                'successful' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('registration_id');

        $this->assertSame(0, RoundResult::query()->count());
    }

    #[Test]
    public function la_consola_corrige_un_resultado_del_torneo(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament, days: 1, rounds: 1);
        $registration = app(RegisterParticipantInCategory::class)->handle(
            $category,
            Participant::factory()->create(),
        );
        $this->startRound($category, 1);
        $this->record($category, $registration, 1, '8.342');

        $result = RoundResult::query()->where('registration_id', $registration->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->put("/admin/tournaments/{$tournament->slug}/competition/results/{$result->id}", [
                'time' => '7.842',
                'successful' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(7842, $result->refresh()->time_ms);
    }

    #[Test]
    public function la_consola_rechaza_corregir_un_resultado_de_otro_torneo(): void
    {
        $otro = $this->tournament('otro-torneo');
        $otraCategoria = $this->category($otro, 'Otra', days: 1, rounds: 1);
        $registrado = app(RegisterParticipantInCategory::class)->handle(
            $otraCategoria,
            Participant::factory()->create(),
        );
        $this->startRound($otraCategoria, 1);
        $this->record($otraCategoria, $registrado, 1, '8.342');

        $result = RoundResult::query()->where('registration_id', $registrado->id)->firstOrFail();

        $tournament = $this->tournament();
        $this->category($tournament, days: 1, rounds: 1);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}/competition")
            ->put("/admin/tournaments/{$tournament->slug}/competition/results/{$result->id}", [
                'time' => '7.000',
                'successful' => true,
            ])
            ->assertSessionHasErrors()
            ->assertRedirect();

        $this->assertSame(8342, $result->refresh()->time_ms);
    }

    // ---------------------------------------------------------------- helpers

    private function tournament(string $slug = 'copa-colombia'): Tournament
    {
        return Tournament::factory()->published()->create(['slug' => $slug]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function category(
        Tournament $tournament,
        string $name = 'Profesional',
        int $days = 1,
        int $rounds = 1,
    ): TournamentCategory {
        return app(SaveTournamentCategory::class)->create($tournament, [
            'name' => $name,
            'number_of_days' => $days,
            'rounds_per_day' => $rounds,
        ]);
    }

    private function day(TournamentCategory $category, int $number): CompetitionDay
    {
        return CompetitionDay::query()
            ->where('tournament_category_id', $category->id)
            ->where('day_number', $number)
            ->firstOrFail();
    }

    private function round(TournamentCategory $category, int $number): Round
    {
        return Round::query()
            ->where('competition_day_id', $this->day($category, 1)->id)
            ->where('round_number', $number)
            ->firstOrFail();
    }

    /**
     * @return list<int>
     */
    private function roundNumbers(CompetitionDay $day): array
    {
        return $day->rounds()
            ->orderBy('round_number')
            ->pluck('round_number')
            ->all();
    }

    private function roundNumber(CompetitionDay $day, int $number): Round
    {
        return Round::query()
            ->where('competition_day_id', $day->id)
            ->where('round_number', $number)
            ->firstOrFail();
    }

    private function startRound(TournamentCategory $category, int $number): void
    {
        app(ManageRoundLifecycle::class)->startRound($this->round($category, $number));
    }

    private function record(
        TournamentCategory $category,
        Registration $registration,
        int $roundNumber,
        string $seconds,
    ): void {
        $round = $this->round($category, $roundNumber);

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['time' => $seconds, 'successful' => true])
        );
    }

    private function recordFailed(
        TournamentCategory $category,
        Registration $registration,
        int $roundNumber,
    ): void {
        $round = $this->round($category, $roundNumber);

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['successful' => false])
        );
    }
}
