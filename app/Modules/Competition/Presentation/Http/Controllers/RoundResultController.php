<?php

declare(strict_types=1);

namespace App\Modules\Competition\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Application\Services\UpdateRoundResult;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Competition\Presentation\Http\Requests\RoundResultRequest;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class RoundResultController extends Controller
{
    public function store(
        RoundResultRequest $request,
        Tournament $tournament,
        TournamentCategory $category,
        Round $round,
        RegisterRoundResult $registerResult
    ): RedirectResponse {
        $validated = [
            ...$request->validated(),
            ...$request->validate([
                'registration_id' => [
                    'required',
                    // La inscripción debe pertenecer a esta categoría: el
                    // dominio lo vuelve a verificar, pero se rechaza antes.
                    Rule::exists('registrations', 'id')
                        ->where('tournament_category_id', $category->id),
                ],
            ]),
        ];

        $registration = Registration::query()
            ->where('id', $validated['registration_id'])
            ->firstOrFail();

        $result = $registerResult->handle(
            $round,
            $registration,
            RoundResultData::fromValidated($validated, $request->user()?->id)
        );

        return back()->with('success', $this->message($registration, $result));
    }

    public function update(
        RoundResultRequest $request,
        Tournament $tournament,
        TournamentCategory $category,
        RoundResult $result,
        UpdateRoundResult $updateResult
    ): RedirectResponse {
        $previous = $result->time_formatted;

        $result = $updateResult->handle(
            $result,
            RoundResultData::fromValidated($request->validated(), $request->user()?->id)
        );

        return back()->with(
            'success',
            $result->successful
                ? "Resultado corregido: {$previous} → {$result->time_formatted}."
                : 'Resultado corregido: lazo marcado como fallido.'
        );
    }

    private function message(Registration $registration, RoundResult $result): string
    {
        $name = $registration->participant->full_name;

        return $result->successful
            ? "{$name}: {$result->time_formatted} registrado."
            : "{$name}: lazo fallido registrado.";
    }
}
