<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    #[Test]
    public function el_panel_exige_autenticacion(): void
    {
        $this->get('/admin/participants')->assertRedirect();
        $this->get('/admin/tournaments')->assertRedirect();
    }

    #[Test]
    public function crea_un_participante_con_identificador_publico_consecutivo(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/participants', [
                'first_name' => 'Carlos',
                'last_name' => 'Pérez',
                'document' => '1098765432',
                'city' => 'Montería',
                'country' => 'Colombia',
                'status' => 'active',
            ])
            ->assertRedirect();

        $participant = Participant::query()->firstOrFail();

        $this->assertSame('RND-000001', $participant->public_id);
        $this->assertSame('Carlos Pérez', $participant->full_name);

        // El segundo continúa la secuencia.
        $this->actingAs($this->admin)->post('/admin/participants', [
            'first_name' => 'Juan',
            'last_name' => 'Rodríguez',
            'status' => 'active',
        ]);

        $this->assertSame('RND-000002', Participant::query()->latest('id')->value('public_id'));
    }

    #[Test]
    public function la_foto_del_jinete_se_guarda_y_llega_como_avatar_a_las_dos_caras(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post('/admin/participants', [
                'first_name' => 'Ana',
                'last_name' => 'Gómez',
                'status' => 'active',
                'photo' => UploadedFile::fake()->image('ana.jpg', 400, 400),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $participant = Participant::query()->firstOrFail();

        $this->assertNotNull($participant->photo_path);
        Storage::disk('public')->assertExists($participant->photo_path);

        $this->actingAs($this->admin)
            ->get('/admin/participants')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('participants.data.0.photo_url', $participant->photo_url)
            );

        $this->get("/jinetes/{$participant->public_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('participant.photo_url', $participant->photo_url)
            );
    }

    #[Test]
    public function rechaza_un_participante_con_documento_duplicado(): void
    {
        Participant::factory()->create(['document' => '1098765432']);

        $this->actingAs($this->admin)
            ->post('/admin/participants', [
                'first_name' => 'Otro',
                'last_name' => 'Jinete',
                'document' => '1098765432',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('document');

        $this->assertSame(1, Participant::query()->count());
    }

    #[Test]
    public function crea_un_caballo_con_identificador_publico(): void
    {
        $participant = Participant::factory()->create();

        $this->actingAs($this->admin)
            ->post('/admin/horses', [
                'name' => 'Relámpago',
                'status' => 'active',
                'participant_public_id' => $participant->public_id,
                'sex' => 'male',
            ])
            ->assertRedirect();

        $horse = Horse::query()->firstOrFail();

        $this->assertSame('H-000001', $horse->public_id);
        $this->assertSame($participant->id, $horse->participant_id);
    }

    #[Test]
    public function crea_un_torneo_con_identificador_por_ano(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/tournaments', [
                'name' => 'Torneo Nacional de Lazo',
                'slug' => '',
                'location' => 'Montería',
                'start_date' => '2026-08-20',
                'end_date' => '2026-08-22',
            ])
            ->assertRedirect();

        $tournament = Tournament::query()->firstOrFail();

        $this->assertSame('T-2026-001', $tournament->public_id);
        $this->assertSame('torneo-nacional-de-lazo', $tournament->slug);
        $this->assertSame(TournamentStatus::Draft, $tournament->status);
    }

    #[Test]
    public function crear_una_categoria_genera_su_calendario(): void
    {
        $tournament = Tournament::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/tournaments/{$tournament->slug}/categories", [
                'name' => 'Profesional',
                'number_of_days' => 3,
                'rounds_per_day' => 4,
            ])
            ->assertRedirect();

        $category = TournamentCategory::query()->firstOrFail();

        $this->assertSame(3, CompetitionDay::query()->where('tournament_category_id', $category->id)->count());
        $this->assertSame(13, Round::query()->count(), '3 × 4 lazos + lazo final del día 3 = 13.');
    }

    #[Test]
    public function no_publica_un_torneo_sin_categorias(): void
    {
        $tournament = Tournament::factory()->create();

        $this->actingAs($this->admin)
            ->put("/admin/tournaments/{$tournament->slug}/status", ['status' => 'published'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TournamentStatus::Draft, $tournament->refresh()->status);
    }

    #[Test]
    public function no_permite_una_transicion_de_estado_invalida(): void
    {
        $tournament = Tournament::factory()->create();
        TournamentCategory::factory()->for($tournament)->create();

        // Borrador → Finalizado no es una transición válida.
        $this->actingAs($this->admin)
            ->put("/admin/tournaments/{$tournament->slug}/status", ['status' => 'finished'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TournamentStatus::Draft, $tournament->refresh()->status);
    }

    #[Test]
    public function inscribe_un_participante_y_rechaza_el_duplicado(): void
    {
        [$tournament, $category] = $this->tournamentWithCategory();
        $participant = Participant::factory()->create();

        $url = "/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/registrations";

        $this->actingAs($this->admin)
            ->post($url, ['participant_public_id' => $participant->public_id])
            ->assertRedirect();

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, Registration::query()->value('registration_number'));

        // Segunda inscripción del mismo jinete en la misma categoría.
        $this->actingAs($this->admin)
            ->post($url, ['participant_public_id' => $participant->public_id])
            ->assertSessionHasErrors();

        $this->assertSame(1, Registration::query()->count());
    }

    #[Test]
    public function no_inscribe_a_un_participante_inactivo(): void
    {
        [$tournament, $category] = $this->tournamentWithCategory();
        $participant = Participant::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(
                "/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/registrations",
                ['participant_public_id' => $participant->public_id]
            )
            ->assertSessionHasErrors();

        $this->assertSame(0, Registration::query()->count());
    }

    #[Test]
    public function registra_un_tiempo_desde_la_consola_y_lo_guarda_en_milisegundos(): void
    {
        [$tournament, $category, $registration, $round] = $this->readyToCompete();

        $this->actingAs($this->admin)
            ->post(
                "/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/rounds/{$round->id}/results",
                [
                    'registration_id' => $registration->id,
                    'time' => '8.342',
                    'successful' => true,
                ]
            )
            ->assertRedirect();

        $result = RoundResult::query()->firstOrFail();

        $this->assertSame(8342, $result->time_ms);
        $this->assertTrue($result->successful);
        $this->assertSame($this->admin->id, $result->recorded_by);
    }

    #[Test]
    public function rechaza_un_tiempo_con_formato_invalido(): void
    {
        [$tournament, $category, $registration, $round] = $this->readyToCompete();

        $this->actingAs($this->admin)
            ->post(
                "/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/rounds/{$round->id}/results",
                [
                    'registration_id' => $registration->id,
                    'time' => '8.3421',
                    'successful' => true,
                ]
            )
            ->assertSessionHasErrors('time');

        $this->assertSame(0, RoundResult::query()->count());
    }

    #[Test]
    public function corregir_un_resultado_queda_registrado_en_auditoria(): void
    {
        [$tournament, $category, $registration, $round] = $this->readyToCompete();

        $base = "/admin/tournaments/{$tournament->slug}/categories/{$category->slug}";

        $this->actingAs($this->admin)->post("{$base}/rounds/{$round->id}/results", [
            'registration_id' => $registration->id,
            'time' => '8.342',
            'successful' => true,
        ]);

        $result = RoundResult::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->put("{$base}/results/{$result->id}", ['time' => '7.842', 'successful' => true])
            ->assertRedirect();

        $this->assertSame(7842, $result->refresh()->time_ms);

        $audit = AuditLog::query()->where('action', 'round_result.updated')->firstOrFail();

        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(8342, $audit->old_value['time_ms']);
        $this->assertSame('8.342', $audit->old_value['time']);
        $this->assertSame(7842, $audit->new_value['time_ms']);
        $this->assertSame('7.842', $audit->new_value['time']);
    }

    #[Test]
    public function la_consola_de_competencia_responde(): void
    {
        [$tournament, $category] = $this->readyToCompete();

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/competition")
            ->assertOk();
    }

    #[Test]
    public function la_consola_de_categoria_solo_lista_finalistas_en_el_lazo_final(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);

        // Doce inscritos corren la ronda regular con tiempos crecientes: los
        // diez mejores clasifican a la final y los últimos dos quedan fuera.
        $inscritos = collect(range(1, 12))
            ->map(fn (int $i): Registration => app(RegisterParticipantInCategory::class)->handle(
                $category,
                Participant::factory()->create(),
            ));

        $inscritos->each(function (Registration $registration, int $index) use ($category): void {
            $this->recordInConsole($category, $registration, 1, sprintf('10.%03d', $index));
        });

        $final = Round::query()
            ->whereIn('competition_day_id', $category->competitionDays()->select('id'))
            ->where('round_number', 2)
            ->firstOrFail();

        $this->assertTrue($final->is_final);

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/competition?round={$final->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('competition/Console')
                ->where('activeRound.is_final', true)
                ->where('activeRound.round_number', 2)
                ->has('entries', 10)
            );
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{Tournament, TournamentCategory}
     */
    private function tournamentWithCategory(int $days = 3, int $rounds = 4): array
    {
        $tournament = Tournament::factory()->published()->create();

        $category = app(SaveTournamentCategory::class)
            ->create($tournament, [
                'name' => 'Profesional',
                'number_of_days' => $days,
                'rounds_per_day' => $rounds,
            ]);

        return [$tournament, $category];
    }

    /**
     * @return array{Tournament, TournamentCategory, Registration, Round}
     */
    private function readyToCompete(): array
    {
        [$tournament, $category] = $this->tournamentWithCategory();

        $registration = app(RegisterParticipantInCategory::class)
            ->handle($category, Participant::factory()->create());

        $round = Round::query()
            ->whereIn(
                'competition_day_id',
                CompetitionDay::query()->where('tournament_category_id', $category->id)->select('id')
            )
            ->orderBy('id')
            ->firstOrFail();

        app(ManageRoundLifecycle::class)->startRound($round);

        return [$tournament, $category, $registration, $round->refresh()];
    }

    private function recordInConsole(
        TournamentCategory $category,
        Registration $registration,
        int $roundNumber,
        string $seconds,
    ): void {
        $round = Round::query()
            ->whereIn('competition_day_id', $category->competitionDays()->select('id'))
            ->where('round_number', $roundNumber)
            ->firstOrFail();

        if ($round->status === RoundStatus::Pending) {
            $round = app(ManageRoundLifecycle::class)->startRound($round);
        }

        app(RegisterRoundResult::class)->handle(
            $round,
            $registration,
            RoundResultData::fromValidated(['time' => $seconds, 'successful' => true])
        );
    }
}
