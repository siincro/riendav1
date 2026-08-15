<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Domain\Models;

use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\ValueObjects\Milliseconds;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posición acumulada de un inscrito dentro de una categoría.
 *
 * Valor derivado: se reconstruye completo cada vez que cambia un resultado.
 *
 * @property int $tournament_category_id
 * @property int $registration_id
 * @property int $total_time_ms
 * @property int $total_penalty_ms
 * @property int $total_successful_rounds
 * @property int $recorded_rounds
 * @property int|null $position
 * @property bool $tied
 */
class CategoryStanding extends Model
{
    protected $fillable = [
        'tournament_category_id',
        'registration_id',
        'total_time_ms',
        'total_penalty_ms',
        'total_successful_rounds',
        'recorded_rounds',
        'position',
        'tied',
    ];

    protected $appends = ['total_time_formatted', 'total_penalty_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_time_ms' => 'integer',
            'total_penalty_ms' => 'integer',
            'total_successful_rounds' => 'integer',
            'recorded_rounds' => 'integer',
            'position' => 'integer',
            'tied' => 'boolean',
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
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function getTotalTimeFormattedAttribute(): string
    {
        return Milliseconds::fromInt($this->total_time_ms)->format();
    }

    /** Cuánto del acumulado es castigo. */
    public function getTotalPenaltyFormattedAttribute(): string
    {
        return Milliseconds::fromInt($this->total_penalty_ms)->format();
    }
}
