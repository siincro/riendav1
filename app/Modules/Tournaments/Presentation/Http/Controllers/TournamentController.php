<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tournaments\Application\Services\ChangeTournamentStatus;
use App\Modules\Tournaments\Application\Services\DeleteTournament;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Presentation\Http\Requests\TournamentRequest;
use App\Modules\Tournaments\Presentation\Http\Resources\TournamentResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function index(Request $request): Response
    {
        $tournaments = Tournament::query()
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->withCount(['categories', 'registrations'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('tournaments/Index', [
            'tournaments' => TournamentResource::collection($tournaments),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'statuses' => TournamentStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tournaments/Form', ['tournament' => null]);
    }

    public function store(TournamentRequest $request): RedirectResponse
    {
        $tournament = Tournament::withPublicIdRetries(
            fn (): Tournament => Tournament::query()->create($this->attributes($request))
        );

        return to_route('admin.tournaments.show', $tournament)
            ->with('success', "Evento creado con el ID {$tournament->public_id}.");
    }

    public function show(Request $request, Tournament $tournament): Response
    {
        $tournament->loadCount('registrations')
            ->load([
                'categories' => fn ($query) => $query->withCount('registrations'),
                'competitionGroups' => fn ($query) => $query->withCount('registrations'),
            ]);

        return Inertia::render('tournaments/Show', [
            'tournament' => (new TournamentResource($tournament))->resolve($request),
        ]);
    }

    public function edit(Request $request, Tournament $tournament): Response
    {
        return Inertia::render('tournaments/Form', [
            'tournament' => (new TournamentResource($tournament))->resolve($request),
        ]);
    }

    public function update(TournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $tournament->fill($this->attributes($request, $tournament))->save();

        return to_route('admin.tournaments.show', $tournament)->with('success', 'Evento actualizado.');
    }

    /**
     * Borra un torneo que todavía no tiene inscritos. Un torneo con jinetes
     * dentro se cancela, no se borra: eso lo decide el servicio.
     */
    public function destroy(Tournament $tournament, DeleteTournament $deleteTournament): RedirectResponse
    {
        $name = $tournament->name;

        $deleteTournament->handle($tournament);

        return to_route('admin.tournaments.index')->with('success', "Evento {$name} eliminado.");
    }

    /**
     * Avanza el estado del torneo (publicar, iniciar, finalizar, cancelar).
     */
    public function changeStatus(
        Request $request,
        Tournament $tournament,
        ChangeTournamentStatus $changeStatus
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', new Enum(TournamentStatus::class)],
        ]);

        $tournament = $changeStatus->handle($tournament, TournamentStatus::from($validated['status']));

        return back()->with('success', "El evento pasó a «{$tournament->status->label()}».");
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(TournamentRequest $request, ?Tournament $tournament = null): array
    {
        $validated = $request->validated();

        $coverPath = $tournament?->cover_image_path;

        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('tournaments', 'public');
        }

        unset($validated['cover_image']);

        return [...$validated, 'cover_image_path' => $coverPath];
    }
}
