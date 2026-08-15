<?php

declare(strict_types=1);

namespace App\Modules\Shared\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen transversal del estado de RIENDA.
 *
 * Es la única vista que cruza módulos, así que vive en el kernel compartido
 * en lugar de pertenecer a uno de ellos.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'participants' => Participant::query()->active()->count(),
                'horses' => Horse::query()->active()->count(),
                'tournaments' => Tournament::query()->count(),
                'results' => RoundResult::query()->count(),
            ],
            'liveCategories' => TournamentCategory::query()
                ->whereHas(
                    'tournament',
                    fn ($query) => $query->where('status', TournamentStatus::InProgress)
                )
                ->with('tournament')
                ->withCount('registrations')
                ->orderBy('order')
                ->get()
                ->map(fn (TournamentCategory $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'status' => $category->status->value,
                    'status_label' => $category->status->label(),
                    'registrations_count' => $category->registrations_count,
                    'tournament' => [
                        'name' => $category->tournament->name,
                        'slug' => $category->tournament->slug,
                    ],
                ])
                ->values(),
            'recentTournaments' => Tournament::query()
                ->withCount('categories')
                ->latest('id')
                ->take(5)
                ->get()
                ->map(fn (Tournament $tournament): array => [
                    'public_id' => $tournament->public_id,
                    'name' => $tournament->name,
                    'slug' => $tournament->slug,
                    'status' => $tournament->status->value,
                    'status_label' => $tournament->status->label(),
                    'categories_count' => $tournament->categories_count,
                ])
                ->values(),
        ]);
    }
}
