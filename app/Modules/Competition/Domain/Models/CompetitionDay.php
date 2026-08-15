<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Models;

use App\Modules\Competition\Domain\Enums\CompetitionDayStatus;
use App\Modules\Competition\Infrastructure\Database\Factories\CompetitionDayFactory;
use App\Modules\Leaderboards\Domain\Models\DailyResult;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un día de competencia dentro de una categoría.
 *
 * @property int $id
 * @property int $tournament_category_id
 * @property int $day_number
 * @property CompetitionDayStatus $status
 * @property Carbon|null $date
 */
class CompetitionDay extends Model
{
    /** @use HasFactory<CompetitionDayFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_category_id',
        'day_number',
        'date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CompetitionDayStatus::class,
            'date' => 'date',
            'day_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TournamentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class, 'tournament_category_id');
    }

    /**
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('round_number');
    }

    /**
     * @return HasMany<DailyResult, $this>
     */
    public function dailyResults(): HasMany
    {
        return $this->hasMany(DailyResult::class);
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }
}
