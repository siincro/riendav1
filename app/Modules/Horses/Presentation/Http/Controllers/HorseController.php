<?php

declare(strict_types=1);

namespace App\Modules\Horses\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Horses\Domain\Enums\HorseSex;
use App\Modules\Horses\Domain\Enums\HorseStatus;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Horses\Presentation\Http\Requests\HorseRequest;
use App\Modules\Horses\Presentation\Http\Resources\HorseResource;
use App\Modules\Participants\Domain\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HorseController extends Controller
{
    public function index(Request $request): Response
    {
        $horses = Horse::query()
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->with('participant')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('horses/Index', [
            'horses' => HorseResource::collection($horses),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'statuses' => HorseStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('horses/Form', [
            'horse' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(HorseRequest $request): RedirectResponse
    {
        $horse = Horse::withPublicIdRetries(
            fn (): Horse => Horse::query()->create($this->attributes($request))
        );

        return to_route('admin.horses.index')
            ->with('success', "Caballo {$horse->name} registrado con el ID {$horse->public_id}.");
    }

    public function edit(Request $request, Horse $horse): Response
    {
        $horse->load('participant');

        return Inertia::render('horses/Form', [
            'horse' => (new HorseResource($horse))->resolve($request),
            ...$this->formOptions(),
        ]);
    }

    public function update(HorseRequest $request, Horse $horse): RedirectResponse
    {
        $horse->fill($this->attributes($request, $horse))->save();

        return to_route('admin.horses.index')->with('success', 'Caballo actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(HorseRequest $request, ?Horse $horse = null): array
    {
        $validated = $request->validated();

        $participantId = null;

        if (filled($validated['participant_public_id'] ?? null)) {
            $participantId = Participant::query()
                ->where('public_id', $validated['participant_public_id'])
                ->value('id');
        }

        $photoPath = $horse?->photo_path;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('horses', 'public');
        }

        return [
            'name' => $validated['name'],
            'status' => $validated['status'],
            'participant_id' => $participantId,
            'owner' => $validated['owner'] ?? null,
            'breed' => $validated['breed'] ?? null,
            'color' => $validated['color'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'photo_path' => $photoPath,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'statuses' => HorseStatus::options(),
            'sexes' => HorseSex::options(),
            'participants' => Participant::query()
                ->active()
                ->orderBy('last_name')
                ->get(['public_id', 'first_name', 'last_name'])
                ->map(fn (Participant $participant): array => [
                    'public_id' => $participant->public_id,
                    'full_name' => $participant->full_name,
                ]),
        ];
    }
}
