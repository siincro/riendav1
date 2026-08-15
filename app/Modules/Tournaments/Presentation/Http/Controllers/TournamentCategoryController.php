<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use App\Modules\Tournaments\Presentation\Http\Requests\TournamentCategoryRequest;
use Illuminate\Http\RedirectResponse;

class TournamentCategoryController extends Controller
{
    public function store(
        TournamentCategoryRequest $request,
        Tournament $tournament,
        SaveTournamentCategory $saveCategory
    ): RedirectResponse {
        $category = $saveCategory->create($tournament, $request->validated());

        return back()->with(
            'success',
            "Categoría {$category->name} creada con {$category->number_of_days} días × {$category->rounds_per_day} lazos."
        );
    }

    public function update(
        TournamentCategoryRequest $request,
        Tournament $tournament,
        TournamentCategory $category,
        SaveTournamentCategory $saveCategory
    ): RedirectResponse {
        $saveCategory->update($category, $request->validated());

        return back()->with('success', 'Categoría actualizada.');
    }
}
