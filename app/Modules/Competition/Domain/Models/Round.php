<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Models;

use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Infrastructure\Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una ronda dentro de un día de competencia.
 *
 * Es la unidad atómica del registro: cada inscrito produce exactamente un
 * resultado por ronda, y de esos resultados se derivan todos los cálculos.
 *
 * @property int $id
 * @property int $competition_day_id
 * @property int $round_number
 * @property bool $is_final
 * @property RoundStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    protected $fillable = [
        'competition_day_id',
        'round_number',
        'is_final',
        'status',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RoundStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'round_number' => 'integer',
            'is_final' => 'boolean',
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
     * @return HasMany<RoundResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(RoundResult::class);
    }

    public function acceptsResults(): bool
    {
        return $this->status->acceptsResults();
    }

    /**
     * Identificador de la categoría a la que pertenece la ronda.
     *
     * Se usa para verificar que una inscripción corresponde efectivamente a
     * la competencia en la que se le quiere registrar un tiempo.
     */
    public function categoryId(): int
    {
        return $this->competitionDay->tournament_category_id;
    }
}
