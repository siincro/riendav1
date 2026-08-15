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
 * RIENDA TV: el overlay para OBS Studio.
 *
 * La pantalla es de solo lectura, no expone ids internos y sirve tanto el hub
 * `/tv` (categoría activa) como la URL fija por slugs que el operador pega en
 * la fuente de navegador de OBS.
 */
class TvOverlayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_hub_tv_sigue_la_categoria_activa_del_torneo_en_curso(): void
    {
        [$tournament, $category, $registrations] = $this->liveScenario();

        $this->get(route('public.tv.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tv/Overlay')
                ->where('tournament.slug', $tournament->slug)
                ->where('tournament.name', $tournament->name)
                ->where('category.slug', $category->slug)
                ->where('category.name', $category->name)
                ->has('standings.data', 2)
                ->where('standings.data.0.position', 1)
                ->where('standings.data.0.registration.participant.full_name', $registrations[0]->participant->full_name)
                ->where('standings.data.0.registration.participant.public_id', $registrations[0]->participant->public_id)
                ->where('standings.data.1.position', 2)
            );
    }

    #[Test]
    public function el_hub_tv_sin_torneo_en_curso_queda_en_espera(): void
    {
        Tournament::factory()->published()->create(['name' => 'Futuro', 'slug' => 'futuro']);

        $this->get(route('public.tv.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tv/Overlay')
                ->where('tournament', null)
                ->where('category', null)
                ->has('standings.data', 0)
            );
    }

    #[Test]
    public function la_url_directa_fija_una_categoria_para_la_fuente_de_obs(): void
    {
        [$tournament, $category, $registrations] = $this->finishedScenario();

        $this->get(route('public.tv.categories.show', [
            'tournament' => $tournament->slug,
            'category' => $category->slug,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tv/Overlay')
                ->where('category.slug', $category->slug)
                ->has('standings.data', 2)
                ->where('standings.data.0.registration.participant.full_name', $registrations[0]->participant->full_name)
            );
    }

    #[Test]
    public function un_torneo_en_borrador_no_tiene_overlay(): void
    {
        [$tournament, $category] = $this->tournamentWithCategory();

        $tournament->status = TournamentStatus::Draft;
        $tournament->save();

        $this->get(route('public.tv.categories.show', [
            'tournament' => $tournament->slug,
            'category' => $category->slug,
        ]))->assertNotFound();
    }

    #[Test]
    public function una_categoria_de_otro_torneo_no_resuelve_en_la_url(): void
    {
        [$firstTournament, $category] = $this->tournamentWithCategory();
        $secondTournament = Tournament::factory()->inProgress()->create(['name' => 'Otro', 'slug' => 'otro']);

        $this->get(route('public.tv.categories.show', [
            'tournament' => $secondTournament->slug,
            'category' => $category->slug,
        ]))->assertNotFound();
    }

    // ------------------------------------------------------------- helpers

    /**
     * @return array{Tournament, TournamentCategory, list<Registration>}
     */
    private function liveScenario(): array
    {
        $tournament = Tournament::factory()->inProgress()->create([
            'name' => 'Torneo Nacional de Lazo',
            'slug' => 'torneo-nacional-de-lazo',
        ]);

        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $registrations = [
            $this->registerWithHorse($category, 'Carlos', 'Pérez'),
            $this->registerWithHorse($category, 'Juan', 'Rodríguez'),
        ];

        $this->record($category, 1, 1, $registrations[0], '8.000');
        $this->record($category, 1, 1, $registrations[1], '8.500');

        return [$tournament, $category, $registrations];
    }

    /**
     * @return array{Tournament, TournamentCategory, list<Registration>}
     */
    private function finishedScenario(): array
    {
        $tournament = Tournament::factory()->inProgress()->create([
            'name' => 'Copa Colombia',
            'slug' => 'copa-colombia',
        ]);

        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 1,
        ]);

        $registrations = [
            $this->registerWithHorse($category, 'Ana', 'Gómez'),
            $this->registerWithHorse($category, 'Luis', 'Mora'),
        ];

        $this->record($category, 1, 1, $registrations[0], '8.000');
        $this->record($category, 1, 1, $registrations[1], '8.500');

        $tournament->status = TournamentStatus::Finished;
        $tournament->save();

        return [$tournament, $category, $registrations];
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
            'rounds_per_day' => 1,
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
