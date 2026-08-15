<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Models;

use App\Models\User;
use App\Modules\Competition\Infrastructure\Database\Factories\RoundResultFactory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\ValueObjects\Milliseconds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El resultado de un inscrito en una ronda: el dato original del sistema.
 *
 * Todo lo demás — resultado diario, acumulado, clasificación, historial,
 * RIENDA TV — se deriva de esta tabla. Nunca se sobrescribe con información
 * calculada, para poder reconstruir cualquier clasificación y auditarla.
 *
 * @property int $id
 * @property int $round_id
 * @property int $registration_id
 * @property int $time_ms
 * @property int $penalty_ms
 * @property bool $successful
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $time_formatted
 * @property-read string $penalty_formatted
 */
class RoundResult extends Model
{
    /** @use HasFactory<RoundResultFactory> */
    use HasFactory;

    protected $fillable = [
        'round_id',
        'registration_id',
        'time_ms',
        'penalty_ms',
        'successful',
        'notes',
        'recorded_by',
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
            'successful' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function time(): Milliseconds
    {
        return Milliseconds::fromInt($this->time_ms);
    }

    public function getTimeFormattedAttribute(): string
    {
        return $this->time()->format();
    }

    public function penalty(): Milliseconds
    {
        return Milliseconds::fromInt($this->penalty_ms);
    }

    public function getPenaltyFormattedAttribute(): string
    {
        return $this->penalty()->format();
    }

    /**
     * El tiempo que este resultado aporta al acumulado: el cronómetro más la
     * penalización que el juez le haya cargado.
     *
     * Un lazo fallido no suma nada, aunque el registro exista.
     */
    public function countableTimeMs(): int
    {
        return $this->successful ? $this->time_ms + $this->penalty_ms : 0;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('successful', true);
    }
}
