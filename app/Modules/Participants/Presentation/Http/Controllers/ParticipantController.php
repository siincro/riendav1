<?php

declare(strict_types=1);

namespace App\Modules\Participants\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Participants\Application\Data\ParticipantData;
use App\Modules\Participants\Application\Services\CreateParticipant;
use App\Modules\Participants\Application\Services\UpdateParticipant;
use App\Modules\Participants\Domain\Enums\ParticipantStatus;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Participants\Presentation\Http\Requests\ParticipantRequest;
use App\Modules\Participants\Presentation\Http\Resources\ParticipantResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantController extends Controller
{
    public function index(Request $request): Response
    {
        $participants = Participant::query()
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->withCount(['horses', 'registrations'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('participants/Index', [
            'participants' => ParticipantResource::collection($participants),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'statuses' => ParticipantStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('participants/Form', [
            'participant' => null,
            'statuses' => ParticipantStatus::options(),
        ]);
    }

    public function store(ParticipantRequest $request, CreateParticipant $createParticipant): RedirectResponse
    {
        $participant = $createParticipant->handle(
            ParticipantData::fromValidated($this->withPhoto($request))
        );

        return to_route('admin.participants.show', $participant)
            ->with('success', "Participante {$participant->full_name} registrado con el ID {$participant->public_id}.");
    }

    public function show(Request $request, Participant $participant): Response
    {
        $participant->loadCount(['horses', 'registrations']);

        return Inertia::render('participants/Show', [
            'participant' => (new ParticipantResource($participant))->resolve($request),
            'horses' => $participant->horses()->orderBy('name')->get()->map(fn ($horse): array => [
                'public_id' => $horse->public_id,
                'name' => $horse->name,
                'status' => $horse->status->value,
            ]),
        ]);
    }

    public function edit(Request $request, Participant $participant): Response
    {
        return Inertia::render('participants/Form', [
            'participant' => (new ParticipantResource($participant))->resolve($request),
            'statuses' => ParticipantStatus::options(),
        ]);
    }

    public function update(
        ParticipantRequest $request,
        Participant $participant,
        UpdateParticipant $updateParticipant
    ): RedirectResponse {
        $updateParticipant->handle(
            $participant,
            ParticipantData::fromValidated($this->withPhoto($request, $participant))
        );

        return to_route('admin.participants.show', $participant)
            ->with('success', 'Participante actualizado.');
    }

    /**
     * Guarda la foto si viene en la petición y devuelve los datos validados
     * con la ruta resultante.
     *
     * @return array<string, mixed>
     */
    protected function withPhoto(ParticipantRequest $request, ?Participant $participant = null): array
    {
        $validated = $request->validated();

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('participants', 'public');
        } elseif ($participant !== null) {
            $validated['photo_path'] = $participant->photo_path;
        }

        unset($validated['photo']);

        return $validated;
    }
}
