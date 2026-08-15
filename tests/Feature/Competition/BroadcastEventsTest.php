<?php

declare(strict_types=1);

namespace Tests\Feature\Competition;

use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Domain\Events\CompetitionDayCompleted;
use App\Modules\Competition\Domain\Events\CompetitionDayStarted;
use App\Modules\Competition\Domain\Events\RoundCompleted;
use App\Modules\Competition\Domain\Events\RoundResultRecorded;
use App\Modules\Competition\Domain\Events\RoundResultUpdated;
use App\Modules\Competition\Domain\Events\RoundStarted;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El motor de competencia difunde sus cambios por Reverb.
 *
 * Los seis eventos de dominio se emiten en un canal privado (consola del
 * administrador) y en un canal público identificado por slugs (landing
 * «en vivo» y RIENDA TV), con un payload sanitizado que no expone ids
 * internos.
 */
class BroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private TournamentCategory $category;

    private CompetitionDay $day;

    private Round $round;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tournament = Tournament::factory()->published()->create([
            'name' => 'Copa Colombia',
            'slug' => 'copa-colombia',
        ]);

        $this->category = app(SaveTournamentCategory::class)->create($this->tournament, [
            'name' => 'Profesional',
            'number_of_days' => 2,
            'rounds_per_day' => 2,
        ]);

        $this->day = CompetitionDay::query()
            ->where('tournament_category_id', $this->category->id)
            ->where('day_number', 1)
            ->firstOrFail();

        $this->round = Round::query()
            ->where('competition_day_id', $this->day->id)
            ->where('round_number', 1)
            ->firstOrFail();
    }

    #[Test]
    public function los_seis_eventos_de_competencia_son_difundibles(): void
    {
        $events = [
            RoundResultRecorded::class,
            RoundResultUpdated::class,
            RoundStarted::class,
            RoundCompleted::class,
            CompetitionDayStarted::class,
            CompetitionDayCompleted::class,
        ];

        foreach ($events as $event) {
            $this->assertTrue(
                is_subclass_of($event, ShouldBroadcast::class),
                "{$event} debe implementar ShouldBroadcast."
            );
        }
    }

    #[Test]
    public function registrar_un_resultado_encola_la_difusion(): void
    {
        Queue::fake();

        $registration = $this->register('Carlos', 'Pérez');
        $this->startRound();

        app(RegisterRoundResult::class)->handle(
            $this->round,
            $registration,
            RoundResultData::fromValidated(['time' => '8.500', 'successful' => true])
        );

        Queue::assertPushed(BroadcastEvent::class);
    }

    #[Test]
    public function un_resultado_se_difunde_en_los_canales_privado_y_publico(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $this->startRound();

        $result = app(RegisterRoundResult::class)->handle(
            $this->round,
            $registration,
            RoundResultData::fromValidated(['time' => '8.500', 'successful' => true])
        );

        $event = new RoundResultRecorded($result);

        [$private, $tournamentChannel, $public] = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $private);
        $this->assertSame("private-category.{$this->category->id}", $private->name);

        $this->assertInstanceOf(PrivateChannel::class, $tournamentChannel);
        $this->assertSame("private-tournament.{$this->tournament->id}", $tournamentChannel->name);

        $this->assertInstanceOf(Channel::class, $public);
        $this->assertSame('public-category.copa-colombia.profesional', $public->name);

        $this->assertSame('round-result.recorded', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($registration->participant->public_id, $payload['participant']);
        $this->assertSame(8500, $payload['time_ms']);
        $this->assertSame(true, $payload['successful']);
        $this->assertSame(1, $payload['day_number']);
        $this->assertSame(1, $payload['round_number']);

        // El payload nunca expone identificadores internos.
        $this->assertArrayNotHasKey('registration_id', $payload);
        $this->assertArrayNotHasKey('round_id', $payload);
        $this->assertArrayNotHasKey('id', $payload);
    }

    #[Test]
    public function un_resultado_corregido_se_difunde_con_su_nombre_de_evento(): void
    {
        $registration = $this->register('Carlos', 'Pérez');
        $this->startRound();

        $result = app(RegisterRoundResult::class)->handle(
            $this->round,
            $registration,
            RoundResultData::fromValidated(['time' => '8.500', 'successful' => true])
        );

        $event = new RoundResultUpdated($result, [
            'time_ms' => 8500,
            'successful' => true,
            'notes' => null,
        ]);

        $this->assertSame('round-result.updated', $event->broadcastAs());
        $this->assertSame($registration->participant->public_id, $event->broadcastWith()['participant']);
    }

    #[Test]
    public function los_eventos_de_ronda_se_difunden_por_la_categoria(): void
    {
        $event = new RoundStarted($this->round);

        [$private, $tournamentChannel, $public] = $event->broadcastOn();

        $this->assertSame("private-category.{$this->category->id}", $private->name);
        $this->assertSame("private-tournament.{$this->tournament->id}", $tournamentChannel->name);
        $this->assertSame('public-category.copa-colombia.profesional', $public->name);
        $this->assertSame('round.started', $event->broadcastAs());
        $this->assertSame(['day_number' => 1, 'round_number' => 1], $event->broadcastWith());
    }

    #[Test]
    public function los_eventos_de_dia_se_difunden_por_la_categoria(): void
    {
        $event = new CompetitionDayStarted($this->day);

        [$private, $tournamentChannel, $public] = $event->broadcastOn();

        $this->assertSame("private-category.{$this->category->id}", $private->name);
        $this->assertSame("private-tournament.{$this->tournament->id}", $tournamentChannel->name);
        $this->assertSame('public-category.copa-colombia.profesional', $public->name);
        $this->assertSame('competition-day.started', $event->broadcastAs());
        $this->assertSame(['day_number' => 1], $event->broadcastWith());
    }

    // ---------------------------------------------------------------- helpers

    private function register(string $firstName, string $lastName): Registration
    {
        return app(RegisterParticipantInCategory::class)->handle(
            $this->category,
            Participant::factory()->create(['first_name' => $firstName, 'last_name' => $lastName])
        );
    }

    private function startRound(): void
    {
        $this->round = app(ManageRoundLifecycle::class)->startRound($this->round);
    }
}
