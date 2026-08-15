<?php

declare(strict_types=1);

namespace App\Modules\Competition\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competition\Application\Services\SaveCompetitionGroup;
use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompetitionGroupController extends Controller
{
    public function store(
        Request $request,
        Tournament $tournament,
        SaveCompetitionGroup $saveGroup
    ): RedirectResponse {
        $validated = $this->validated($request, $tournament);

        $group = $saveGroup->create($tournament, $validated);

        return back()->with('success', "Grupo «{$group->name}» creado.");
    }

    public function update(
        Request $request,
        Tournament $tournament,
        CompetitionGroup $group,
        SaveCompetitionGroup $saveGroup
    ): RedirectResponse {
        $validated = $this->validated($request, $tournament, $group);

        $saveGroup->update($group, $validated);

        return back()->with('success', "Grupo «{$group->refresh()->name}» actualizado.");
    }

    public function destroy(
        Tournament $tournament,
        CompetitionGroup $group,
        SaveCompetitionGroup $saveGroup
    ): RedirectResponse {
        $name = $group->name;

        $saveGroup->delete($group);

        return back()->with('success', "Grupo «{$name}» eliminado.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Tournament $tournament, ?CompetitionGroup $group = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('competition_groups', 'name')
                    ->where('tournament_id', $tournament->id)
                    ->ignore($group?->id),
            ],
            'order' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], [
            'name' => 'nombre del grupo',
            'order' => 'orden',
        ]);
    }
}
