<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Domain\Models;

use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\ValueObjects\Milliseconds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado de un inscrito en un día de competencia.
 *
 * Valor derivado de `round_results`: se recalcula, nunca se edita a mano.
 *
 * @property int $competition_day_id
 * @property int $registration_id
 * @property int $time_ms
 * @property int $penalty_ms
 * @property int $successful_rounds
 * @property int $recorded_rounds
 */
class DailyResult extends Model
{
    protected $fillable = [
        'competition_day_id',
        'registration_id',
        'time_ms',
        'penalty_ms',
        'successful_rounds',
        'recorded_rounds',
    ];

    protected $appends = ['time_formatted', 'penalty_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_ms' => 'integer',
            'penalty_ms' => 'integer',
            'successful_rounds' => 'integer',
            'recorded_rounds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CompetitionDay, $this>
     */
    public function competitionDay(): BelongsTo
    {
        return $this->belongsTo(CompetitionDay::class);
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function getTimeFormattedAttribute(): string
    {
        return Milliseconds::fromInt($this->time_ms)->format();
    }

    /** Cuánto del tiempo del día es castigo. */
    public function getPenaltyFormattedAttribute(): string
    {
        return Milliseconds::fromInt($this->penalty_ms)->format();
    }
}
