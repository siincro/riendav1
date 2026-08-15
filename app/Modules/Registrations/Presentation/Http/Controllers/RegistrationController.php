<?php

declare(strict_types=1);

namespace App\Modules\Registrations\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Registrations\Domain\Enums\RegistrationStatus;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Registrations\Presentation\Http\Resources\RegistrationResource;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use App\Modules\Tournaments\Presentation\Http\Resources\TournamentCategoryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function index(Request $request, Tournament $tournament, TournamentCategory $category): Response
    {
        $category->load('tournament');

        return Inertia::render('registrations/Index', [
            'category' => (new TournamentCategoryResource($category))->resolve($request),
            'registrations' => RegistrationResource::collection(
                $category->registrations()
                    ->with(['participant', 'horse', 'standing', 'competitionGroup'])
                    ->orderBy('registration_number')
                    ->get()
            ),
            'statuses' => RegistrationStatus::options(),
            'availableParticipants' => $this->availableParticipants($category),
            'groups' => $this->availableGroups($category),
        ]);
    }

    public function store(
        Request $request,
        Tournament $tournament,
        TournamentCategory $category,
        RegisterParticipantInCategory $register
    ): RedirectResponse {
        $validated = $request->validate([
            'participant_public_id' => ['required', 'string', Rule::exists('participants', 'public_id')],
            'horse_public_id' => ['nullable', 'string', Rule::exists('horses', 'public_id')],
            'registration_number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'competition_group_id' => [
                'nullable',
                'integer',
                Rule::exists('competition_groups', 'id')
                    ->where('tournament_id', $category->tournament_id),
            ],
        ], [], [
            'participant_public_id' => 'vaquero',
            'horse_public_id' => 'caballo',
            'registration_number' => 'dorsal',
            'competition_group_id' => 'grupo de salida',
        ]);

        $participant = Participant::query()->where('public_id', $validated['participant_public_id'])->firstOrFail();

        $horse = filled($validated['horse_public_id'] ?? null)
            ? Horse::query()->where('public_id', $validated['horse_public_id'])->first()
            : null;

        $group = $request->filled('competition_group_id')
            ? CompetitionGroup::query()->find($request->integer('competition_group_id'))
            : null;

        $registration = $register->handle(
            $category,
            $participant,
            $horse,
            $validated['registration_number'] ?? null,
            $group,
        );

        return back()->with(
            'success',
            "{$participant->full_name} inscrito con el dorsal {$registration->registration_number}."
        );
    }

    /**
     * Cambia el caballo o el estado de una inscripción.
     *
     * Los resultados ya registrados no se tocan: son historial.
     */
    public function update(
        Request $request,
        Tournament $tournament,
        TournamentCategory $category,
        Registration $registration
    ): RedirectResponse {
        $validated = $request->validate([
            'horse_public_id' => ['nullable', 'string', Rule::exists('horses', 'public_id')],
            'status' => ['required', new Enum(RegistrationStatus::class)],
            'competition_group_id' => [
                'nullable',
                'integer',
                Rule::exists('competition_groups', 'id')
                    ->where('tournament_id', $category->tournament_id),
            ],
        ], [], [
            'competition_group_id' => 'grupo de salida',
        ]);

        $registration->fill([
            'horse_id' => filled($validated['horse_public_id'] ?? null)
                ? Horse::query()->where('public_id', $validated['horse_public_id'])->value('id')
                : null,
            'competition_group_id' => $validated['competition_group_id'] ?? null,
            'status' => $validated['status'],
        ])->save();

        return back()->with('success', 'Inscripción actualizada.');
    }

    /**
     * Jinetes activos que todavía no están inscritos en esta categoría, con
     * sus caballos, para poblar el formulario de inscripción.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availableParticipants(TournamentCategory $category): array
    {
        return Participant::query()
            ->active()
            ->whereDoesntHave(
                'registrations',
                fn ($query) => $query->where('tournament_category_id', $category->id)
            )
            ->with(['horses' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('last_name')
            ->get()
            ->map(fn (Participant $participant): array => [
                'public_id' => $participant->public_id,
                'full_name' => $participant->full_name,
                'horses' => $participant->horses->map(fn (Horse $horse): array => [
                    'public_id' => $horse->public_id,
                    'name' => $horse->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Grupos de salida del evento, para asignarlos al inscribir o corregir
     * una inscripción.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availableGroups(TournamentCategory $category): array
    {
        return $category->tournament->competitionGroups()
            ->get()
            ->map(fn (CompetitionGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'order' => $group->order,
            ])
            ->values()
            ->all();
    }
}
