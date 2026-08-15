<?php

declare(strict_types=1);

namespace Tests\Feature\Challenges;

use App\Models\User;
use App\Modules\Challenges\Application\Services\SaveChallenge;
use App\Modules\Challenges\Application\Services\SettleChallenge;
use App\Modules\Challenges\Domain\Enums\ChallengeStatus;
use App\Modules\Challenges\Domain\Models\Challenge;
use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Participants\Domain\Enums\ParticipantStatus;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los retos entre vaqueros.
 *
 * Un reto no cronometra nada: enfrenta a dos inscritos de la misma categoría y
 * lo gana quien más lazos acierte, con el menor tiempo acumulado como
 * desempate. Se mueve solo con cada lazo que registra el juez.
 */
class ChallengesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Tournament $tournament;

    private TournamentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        $this->tournament = Tournament::factory()->published()->create([
            'name' => 'Copa Colombia',
            'slug' => 'copa-colombia',
        ]);

        $this->category = app(SaveTournamentCategory::class)->create($this->tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);
    }

    #[Test]
    public function con_los_mismos_aciertos_gana_el_menor_tiempo(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $challenge = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        // Los dos aciertan sus dos lazos: Carlos acumula 17.000 y Luis 16.000.
        $this->record($carlos, 1, '8.000');
        $this->record($carlos, 2, '9.000');
        $this->record($luis, 1, '7.500');
        $this->record($luis, 2, '8.500');

        $challenge->load(['challenger.standings.category', 'rival.standings.category']);

        $this->assertSame($luis->participant_id, $challenge->leader()?->id);
        $this->assertFalse($challenge->isTied());
    }

    #[Test]
    public function mas_aciertos_gana_aunque_acumule_mas_tiempo(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $challenge = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        // Carlos acierta los dos lazos y acumula 17.000; Luis acierta uno solo
        // y acumula 7.500. Manda el acierto, no el reloj.
        $this->record($carlos, 1, '8.000');
        $this->record($carlos, 2, '9.000');
        $this->record($luis, 1, '7.500');
        $this->recordFailed($luis, 2);

        $challenge->load(['challenger.standings.category', 'rival.standings.category']);

        $this->assertSame($carlos->participant_id, $challenge->leader()?->id);
    }

    #[Test]
    public function sin_tiempos_todavia_no_hay_lider(): void
    {
        $challenge = $this->challengeBetween('Carlos', 'Pérez', 'Luis', 'Gómez');

        $this->assertNull($challenge->leader());
    }

    #[Test]
    public function los_mismos_aciertos_y_el_mismo_tiempo_dejan_el_reto_empatado(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $challenge = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        $this->record($carlos, 1, '8.000');
        $this->record($luis, 1, '8.000');

        $challenge->load(['challenger.standings.category', 'rival.standings.category']);

        $this->assertTrue($challenge->isTied());
        $this->assertNull($challenge->leader());
    }

    #[Test]
    public function quien_no_ha_acertado_ningun_lazo_no_gana_por_no_tener_tiempo(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $challenge = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        // Luis falla su lazo: acumula 0 ms, que no debe ganarle a los 8.000
        // de Carlos.
        $this->record($carlos, 1, '8.000');
        $this->recordFailed($luis, 1);

        $challenge->load(['challenger.standings.category', 'rival.standings.category']);

        $this->assertSame($carlos->participant_id, $challenge->leader()?->id);
    }

    #[Test]
    public function no_se_reta_a_si_mismo(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');

        try {
            app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $carlos->participant);
            $this->fail('Se permitió un reto contra uno mismo.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('sí mismo', $exception->getMessage());
        }
    }

    #[Test]
    public function se_reta_a_un_vaquero_de_otra_categoria_del_mismo_evento(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');

        $otra = app(SaveTournamentCategory::class)->create($this->tournament, [
            'name' => 'Damas',
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);

        $ajena = app(RegisterParticipantInCategory::class)
            ->handle($otra, Participant::factory()->create(['first_name' => 'Ana', 'last_name' => 'Gómez']));

        $challenge = app(SaveChallenge::class)
            ->create($this->tournament, $carlos->participant, $ajena->participant);

        $this->assertSame($carlos->participant_id, $challenge->challenger_participant_id);
        $this->assertSame($ajena->participant_id, $challenge->rival_participant_id);
    }

    #[Test]
    public function no_se_reta_a_quien_no_corre_el_evento(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');

        // Ana existe y está activa, pero no está inscrita en el evento.
        $ana = Participant::factory()->create(['first_name' => 'Ana', 'last_name' => 'Gómez']);

        try {
            app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $ana);
            $this->fail('Se permitió retar a alguien de fuera del evento.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('no está inscrito', $exception->getMessage());
        }
    }

    #[Test]
    public function el_mismo_par_no_se_reta_dos_veces(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        $this->expectException(DomainException::class);

        // Da igual quién rete a quién: el par ya está enfrentado.
        app(SaveChallenge::class)->create($this->tournament, $luis->participant, $carlos->participant);
    }

    #[Test]
    public function no_entra_al_reto_un_vaquero_inactivo(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $luis->participant->update(['status' => ParticipantStatus::Inactive]);

        $this->expectException(DomainException::class);

        app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant->refresh());
    }

    #[Test]
    public function resolver_congela_al_ganador_aunque_despues_cambien_los_tiempos(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $challenge = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        $this->record($carlos, 1, '8.000');
        $this->record($luis, 1, '9.000');

        $challenge = app(SettleChallenge::class)->settle($challenge);

        $this->assertSame(ChallengeStatus::Settled, $challenge->status);
        $this->assertSame($carlos->participant_id, $challenge->winner_participant_id);
        $this->assertNotNull($challenge->settled_at);

        // Aunque Luis mejore después, el reto ya está escrito.
        $this->record($luis, 2, '1.000');

        $this->assertSame($carlos->participant_id, $challenge->refresh()->winner_participant_id);
    }

    #[Test]
    public function un_reto_empatado_no_se_puede_resolver(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $challenge = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        $this->record($carlos, 1, '8.000');
        $this->record($luis, 1, '8.000');

        $this->expectException(DomainException::class);

        app(SettleChallenge::class)->settle($challenge);
    }

    #[Test]
    public function el_panel_crea_un_reto_eligiendo_dos_vaqueros(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        $this->actingAs($this->admin)
            ->from('/admin/challenges')
            ->post('/admin/challenges', [
                'tournament_slug' => $this->tournament->slug,
                'challenger_public_id' => $carlos->participant->public_id,
                'rival_public_id' => $luis->participant->public_id,
            ])
            ->assertRedirect('/admin/challenges')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $challenge = Challenge::query()->firstOrFail();

        $this->assertSame('RETO-000001', $challenge->public_id);
        $this->assertSame($carlos->participant_id, $challenge->challenger_participant_id);
        $this->assertSame($luis->participant_id, $challenge->rival_participant_id);
    }

    #[Test]
    public function el_panel_lista_los_retos_con_su_marcador(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');

        app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);

        $this->record($carlos, 1, '8.000');
        $this->record($luis, 1, '9.000');

        $this->actingAs($this->admin)
            ->get('/admin/challenges')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('challenges/Index')
                ->has('challenges.data', 1)
                ->where('challenges.data.0.contenders.0.participant.full_name', 'Carlos Pérez')
                ->where('challenges.data.0.contenders.0.is_leader', true)
                ->where('challenges.data.0.contenders.1.is_leader', false)
                ->where('challenges.data.0.status', 'open')
                ->has('events.0.contenders', 2)
            );
    }

    #[Test]
    public function la_categoria_publica_muestra_los_retos_de_sus_inscritos(): void
    {
        $carlos = $this->register('Carlos', 'Pérez');
        $luis = $this->register('Luis', 'Gómez');
        $pedro = $this->register('Pedro', 'López');

        $visible = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $luis->participant);
        $cancelado = app(SaveChallenge::class)->create($this->tournament, $carlos->participant, $pedro->participant);

        app(SettleChallenge::class)->cancel($cancelado);

        $this->get("/torneos/{$this->tournament->slug}/categorias/{$this->category->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tournaments/Category')
                ->has('challenges.data', 1)
                ->where('challenges.data.0.public_id', $visible->public_id)
            );
    }

    // ------------------------------------------------------------- ayudantes

    private function challengeBetween(
        string $firstName,
        string $lastName,
        string $rivalFirstName,
        string $rivalLastName
    ): Challenge {
        $challenge = app(SaveChallenge::class)->create(
            $this->tournament,
            $this->register($firstName, $lastName)->participant,
            $this->register($rivalFirstName, $rivalLastName)->participant,
        );

        return $challenge->load(['challenger.standings.category', 'rival.standings.category']);
    }

    private function register(string $firstName, string $lastName): Registration
    {
        return app(RegisterParticipantInCategory::class)->handle(
            $this->category,
            Participant::factory()->create(['first_name' => $firstName, 'last_name' => $lastName]),
        );
    }

    private function record(Registration $registration, int $roundNumber, string $seconds): void
    {
        app(RegisterRoundResult::class)->handle(
            $this->round($roundNumber),
            $registration,
            RoundResultData::fromValidated(['time' => $seconds, 'successful' => true]),
        );
    }

    private function recordFailed(Registration $registration, int $roundNumber): void
    {
        app(RegisterRoundResult::class)->handle(
            $this->round($roundNumber),
            $registration,
            RoundResultData::fromValidated(['time' => null, 'successful' => false]),
        );
    }

    private function round(int $number): Round
    {
        $day = $this->category->competitionDays()->where('day_number', 1)->firstOrFail();

        $round = $day->rounds()->where('round_number', $number)->firstOrFail();

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        return $round;
    }
}
