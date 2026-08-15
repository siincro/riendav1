<?php

declare(strict_types=1);

namespace Tests\Feature\Competition;

use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Application\Services\UpdateRoundResult;
use App\Modules\Competition\Domain\Enums\CompetitionDayStatus;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Leaderboards\Domain\Models\DailyResult;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Enums\RegistrationStatus;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\ValueObjects\Milliseconds;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Enums\CategoryStatus;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ejercita el motor de competencia con el ejemplo del documento de
 * requerimientos, de punta a punta.
 */
class CompetitionEngineTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private TournamentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tournament = Tournament::factory()->published()->create([
            'name' => 'Torneo Nacional de Lazo',
            'slug' => 'torneo-nacional-de-lazo',
            'start_date' => '2026-08-20',
        ]);

        $this->category = app(SaveTournamentCategory::class)->create($this->tournament, [
            'name' => 'Profesional',
            'number_of_days' => 3,
            'rounds_per_day' => 4,
        ]);
    }

    #[Test]
    public function no_se_puede_iniciar_una_ronda_sin_vaqueros_inscritos(): void
    {
        $round = $this->round($this->day(1), 1);

        try {
            app(ManageRoundLifecycle::class)->startRound($round);
            $this->fail('Se inició la ronda de una categoría vacía.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('no tiene vaqueros inscritos', $exception->getMessage());
        }

        // Nada se movió: ni la ronda, ni el día, ni la categoría o el torneo.
        $this->assertSame(RoundStatus::Pending, $round->refresh()->status);
        $this->assertSame(CompetitionDayStatus::Pending, $this->day(1)->status);
        $this->assertSame(CategoryStatus::Pending, $this->category->refresh()->status);
        $this->assertSame(TournamentStatus::Published, $this->tournament->refresh()->status);
        $this->assertTrue($this->category->structureIsEditable());
    }

    #[Test]
    public function no_se_puede_iniciar_un_dia_sin_vaqueros_inscritos(): void
    {
        $this->expectException(DomainException::class);

        app(ManageRoundLifecycle::class)->startDay($this->day(1));
    }

    #[Test]
    public function una_categoria_con_todos_los_inscritos_retirados_no_arranca(): void
    {
        $registration = $this->register('Ana', 'Gómez');
        $registration->update(['status' => RegistrationStatus::Withdrawn]);

        $this->expectException(DomainException::class);

        app(ManageRoundLifecycle::class)->startRound($this->round($this->day(1), 1));
    }

    #[Test]
    public function definir_la_estructura_genera_dias_y_rondas(): void
    {
        $days = CompetitionDay::query()
            ->where('tournament_category_id', $this->category->id)
            ->get();

        $this->assertCount(3, $days, 'Deben crearse 3 días de competencia.');

        $this->assertSame(
            13,
            Round::query()->whereIn('competition_day_id', $days->pluck('id'))->count(),
            '4 rondas por día más el lazo final del último día: 3 × 4 + 1 = 13.'
        );

        // Las fechas se derivan del inicio del torneo.
        $this->assertSame('2026-08-20', $days[0]->date->toDateString());
        $this->assertSame('2026-08-21', $days[1]->date->toDateString());
        $this->assertSame('2026-08-22', $days[2]->date->toDateString());
    }

    #[Test]
    public function el_resultado_diario_suma_solo_las_rondas_acertadas(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $day = $this->day(1);

        // R1 8.342 ✓ · R2 7.521 ✓ · R3 fallida · R4 9.112 ✓
        $this->record($registration, $day, 1, '8.342');
        $this->record($registration, $day, 2, '7.521');
        $this->recordFailed($registration, $day, 3);
        $this->record($registration, $day, 4, '9.112');

        $daily = DailyResult::query()
            ->where('competition_day_id', $day->id)
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        $this->assertSame(24975, $daily->time_ms, 'Tiempo del día: 8.342 + 7.521 + 9.112 = 24.975');
        $this->assertSame('24.975', $daily->time_formatted);
        $this->assertSame(3, $daily->successful_rounds);
        $this->assertSame(4, $daily->recorded_rounds, 'La ronda fallida también queda registrada.');
    }

    #[Test]
    public function la_ronda_fallida_no_acumula_tiempo_aunque_se_capture_uno(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $day = $this->day(1);

        $round = app(ManageRoundLifecycle::class)
            ->startRound($this->round($day, 1));

        // El administrador marca la ronda como fallida pero deja un tiempo
        // escrito en el formulario: el dominio lo descarta.
        app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['time' => '8.342', 'successful' => false])
        );

        $result = RoundResult::query()->where('registration_id', $registration->id)->firstOrFail();

        $this->assertFalse($result->successful);
        $this->assertSame(0, $result->time_ms);
        $this->assertSame(0, $result->countableTimeMs());
    }

    #[Test]
    public function la_penalizacion_se_suma_al_tiempo_del_lazo(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $day = $this->day(1);

        // 8.000 de cronómetro con 5.000 de castigo cuentan como 13.000.
        $this->recordWithPenalty($registration, $day, 1, '8.000', '5.000');
        $this->record($registration, $day, 2, '7.000');

        $daily = DailyResult::query()
            ->where('competition_day_id', $day->id)
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        $this->assertSame(20000, $daily->time_ms, 'Día: 8.000 + 5.000 + 7.000 = 20.000');
        $this->assertSame(5000, $daily->penalty_ms, 'Del día, 5.000 son castigo.');
        $this->assertSame(2, $daily->successful_rounds);

        $standing = CategoryStanding::query()
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        $this->assertSame(20000, $standing->total_time_ms);
        $this->assertSame(5000, $standing->total_penalty_ms);
    }

    #[Test]
    public function un_lazo_fallido_descarta_su_penalizacion(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $day = $this->day(1);

        $round = $this->round($day, 1);
        app(ManageRoundLifecycle::class)->startRound($round);

        // El juez marca fallo pero deja escritos tiempo y castigo: se descartan.
        app(RegisterRoundResult::class)->handle(
            $round->refresh(),
            $registration,
            RoundResultData::fromValidated([
                'time' => '8.000',
                'penalty' => '5.000',
                'successful' => false,
            ])
        );

        $result = RoundResult::query()->where('registration_id', $registration->id)->firstOrFail();

        $this->assertSame(0, $result->time_ms);
        $this->assertSame(0, $result->penalty_ms);
        $this->assertSame(0, $result->countableTimeMs());
    }

    #[Test]
    public function el_acumulado_del_torneo_suma_los_resultados_diarios(): void
    {
        $registration = $this->register('Carlos', 'Pérez');

        // Día 1 → 3 aciertos, 24.975
        $this->recordDay($registration, 1, ['8.342', '7.521', null, '9.112']);
        // Día 2 → 4 aciertos, 29.421
        $this->recordDay($registration, 2, ['7.100', '7.221', '7.500', '7.600']);
        // Día 3 → 3 aciertos, 21.812
        $this->recordDay($registration, 3, ['7.200', '7.312', null, '7.300']);

        $standing = CategoryStanding::query()
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        $this->assertSame(10, $standing->total_successful_rounds);
        $this->assertSame(76208, $standing->total_time_ms);
        $this->assertSame('76.208', $standing->total_time_formatted);
    }

    #[Test]
    public function la_clasificacion_prioriza_aciertos_sobre_tiempo(): void
    {
        // El caso explícito del documento: Pedro tiene el mejor tiempo pero
        // menos rondas acertadas, así que queda tercero.
        $carlos = $this->standingFor('Carlos', 'Pérez', successful: 10, timeMs: 76208);
        $juan = $this->standingFor('Juan', 'Rodríguez', successful: 10, timeMs: 81421);
        $pedro = $this->standingFor('Pedro', 'López', successful: 9, timeMs: 68100);

        // La clasificación es global: se relee una vez está completo el cuadro.
        $carlos->refresh();
        $juan->refresh();
        $pedro->refresh();

        $this->assertSame(10, $carlos->total_successful_rounds);
        $this->assertSame(76208, $carlos->total_time_ms);
        $this->assertSame(68100, $pedro->total_time_ms);

        $this->assertSame(1, $carlos->position);
        $this->assertSame(2, $juan->position);
        $this->assertSame(3, $pedro->position);

        $this->assertFalse($carlos->tied);
        $this->assertFalse($juan->tied);
    }

    #[Test]
    public function los_empates_comparten_posicion_sin_inventar_un_tercer_criterio(): void
    {
        $primero = $this->standingFor('Ana', 'Gómez', successful: 10, timeMs: 70000);
        $empatadoA = $this->standingFor('Luis', 'Mora', successful: 9, timeMs: 72000);
        $empatadoB = $this->standingFor('Iván', 'Ruiz', successful: 9, timeMs: 72000);
        $ultimo = $this->standingFor('Sara', 'Díaz', successful: 8, timeMs: 60000);

        $primero->refresh();
        $empatadoA->refresh();
        $empatadoB->refresh();
        $ultimo->refresh();

        $this->assertSame(1, $primero->position);
        $this->assertSame(2, $empatadoA->position);
        $this->assertSame(2, $empatadoB->position, 'Mismos aciertos y mismo tiempo comparten posición.');
        $this->assertSame(4, $ultimo->position, 'Tras un empate doble, la siguiente posición es la 4.');

        $this->assertTrue($empatadoA->tied);
        $this->assertTrue($empatadoB->tied);
        $this->assertFalse($primero->tied);
    }

    #[Test]
    public function corregir_un_resultado_recalcula_dia_acumulado_y_clasificacion(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $juan = $this->register('Juan', 'Rodríguez');

        $day = $this->day(1);

        $this->record($carlos, $day, 1, '8.342');
        $this->record($juan, $day, 1, '8.000');

        $this->assertSame(2, $this->standing($carlos)->position, 'Carlos arranca segundo.');

        // Antes: 8.342 → Después: 7.842
        $result = RoundResult::query()
            ->where('registration_id', $carlos->id)
            ->firstOrFail();

        app(UpdateRoundResult::class)->handle(
            $result,
            new RoundResultData(Milliseconds::fromSeconds('7.842'), successful: true)
        );

        $daily = DailyResult::query()
            ->where('registration_id', $carlos->id)
            ->firstOrFail();

        $this->assertSame(7842, $daily->time_ms, 'El día se recalcula.');
        $this->assertSame(7842, $this->standing($carlos)->total_time_ms, 'El acumulado se recalcula.');
        $this->assertSame(1, $this->standing($carlos)->position, 'La clasificación se reordena sola.');
        $this->assertSame(2, $this->standing($juan)->position);
    }

    #[Test]
    public function no_se_registra_dos_veces_el_mismo_inscrito_en_una_ronda(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $day = $this->day(1);

        $this->record($registration, $day, 1, '8.342');

        $this->expectException(DomainException::class);

        $this->record($registration, $day, 1, '9.000');
    }

    #[Test]
    public function no_se_registra_un_resultado_de_una_inscripcion_de_otra_categoria(): void
    {
        $otraCategoria = app(SaveTournamentCategory::class)->create($this->tournament, [
            'name' => 'Amateur',
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);

        $ajena = app(RegisterParticipantInCategory::class)->handle(
            $otraCategoria,
            Participant::factory()->create()
        );

        // La categoría propia necesita al menos un inscrito para poder arrancar.
        $this->register('Carlos', 'Pérez');

        $day = $this->day(1);
        $round = $this->round($day, 1);
        app(ManageRoundLifecycle::class)->startRound($round);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La inscripción no pertenece a la categoría de este lazo.');

        app(RegisterRoundResult::class)->handle(
            $round,
            $ajena,
            RoundResultData::fromValidated(['time' => '8.000', 'successful' => true])
        );
    }

    #[Test]
    public function no_se_registran_resultados_en_un_lazo_pendiente(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $round = $this->round($this->day(1), 1);

        $this->assertSame(RoundStatus::Pending, $round->status);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El lazo no está abierto para registrar resultados.');

        app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['time' => '8.000', 'successful' => true])
        );
    }

    #[Test]
    public function un_lazo_acertado_exige_tiempo_mayor_que_cero(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $round = $this->round($this->day(1), 1);
        app(ManageRoundLifecycle::class)->startRound($round);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Un lazo acertado debe tener un tiempo mayor que cero.');

        app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            new RoundResultData(Milliseconds::zero(), successful: true)
        );
    }

    // ---------------------------------------------------------------- helpers

    private function register(string $firstName, string $lastName): Registration
    {
        return app(RegisterParticipantInCategory::class)->handle(
            $this->category,
            Participant::factory()->create(['first_name' => $firstName, 'last_name' => $lastName])
        );
    }

    private function day(int $number): CompetitionDay
    {
        return CompetitionDay::query()
            ->where('tournament_category_id', $this->category->id)
            ->where('day_number', $number)
            ->firstOrFail();
    }

    private function round(CompetitionDay $day, int $number): Round
    {
        return Round::query()
            ->where('competition_day_id', $day->id)
            ->where('round_number', $number)
            ->firstOrFail();
    }

    private function recordWithPenalty(
        Registration $registration,
        CompetitionDay $day,
        int $roundNumber,
        string $seconds,
        string $penalty
    ): RoundResult {
        $round = $this->round($day, $roundNumber);

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        return app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated([
                'time' => $seconds,
                'penalty' => $penalty,
                'successful' => true,
            ])
        );
    }

    private function record(Registration $registration, CompetitionDay $day, int $roundNumber, string $seconds): RoundResult
    {
        $round = $this->round($day, $roundNumber);

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        return app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['time' => $seconds, 'successful' => true])
        );
    }

    private function recordFailed(Registration $registration, CompetitionDay $day, int $roundNumber): RoundResult
    {
        $round = $this->round($day, $roundNumber);

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        return app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['successful' => false])
        );
    }

    /**
     * @param  list<string|null>  $times  null = ronda fallida
     */
    private function recordDay(Registration $registration, int $dayNumber, array $times): void
    {
        $day = $this->day($dayNumber);

        foreach ($times as $index => $seconds) {
            $roundNumber = $index + 1;

            $seconds === null
                ? $this->recordFailed($registration, $day, $roundNumber)
                : $this->record($registration, $day, $roundNumber, $seconds);
        }
    }

    private function standing(Registration $registration): CategoryStanding
    {
        return CategoryStanding::query()
            ->where('registration_id', $registration->id)
            ->firstOrFail();
    }

    /**
     * Crea un inscrito cuyo acumulado da exactamente los valores pedidos, a
     * través de resultados de ronda reales — no escribiendo la clasificación
     * a mano.
     */
    private function standingFor(string $firstName, string $lastName, int $successful, int $timeMs): CategoryStanding
    {
        $registration = $this->register($firstName, $lastName);
        $day = $this->day(1);

        // Reparte el tiempo total entre las rondas acertadas.
        $base = intdiv($timeMs, $successful);
        $remainder = $timeMs - ($base * $successful);

        for ($roundNumber = 1; $roundNumber <= $successful; $roundNumber++) {
            $slice = $base + ($roundNumber === 1 ? $remainder : 0);

            $round = $this->round($day, min($roundNumber, 4));

            // La categoría tiene 4 rondas por día: los aciertos que sobran se
            // reparten en los días siguientes.
            if ($roundNumber > 4) {
                $round = $this->round($this->day(intdiv($roundNumber - 1, 4) + 1), (($roundNumber - 1) % 4) + 1);
            }

            if ($round->status === RoundStatus::Pending) {
                $round = app(ManageRoundLifecycle::class)->startRound($round);
            }

            app(RegisterRoundResult::class)->handle(
                $round,
                $registration,
                RoundResultData::fromValidated([
                    'time' => sprintf('%d.%03d', intdiv($slice, 1000), $slice % 1000),
                    'successful' => true,
                ])
            );
        }

        return $this->standing($registration);
    }
}
