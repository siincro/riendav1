<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Domain\Models;

use App\Modules\Challenges\Domain\Enums\ChallengeStatus;
use App\Modules\Challenges\Infrastructure\Database\Factories\ChallengeFactory;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un reto entre dos vaqueros de un mismo evento.
 *
 * No es una competencia aparte ni obliga a que los dos corran la misma
 * categoría: a cada uno se le mira lo que lleva acumulado en la suya dentro de
 * ese evento —aciertos primero, tiempo como desempate—, así que el reto se
 * mueve solo con cada lazo que el juez registra, sin que nadie anote nada.
 *
 * @property int $id
 * @property string $public_id
 * @property int $tournament_id
 * @property int $challenger_participant_id
 * @property int $rival_participant_id
 * @property int|null $winner_participant_id
 * @property ChallengeStatus $status
 * @property Carbon|null $settled_at
 */
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'tournament_id',
        'challenger_participant_id',
        'rival_participant_id',
        'winner_participant_id',
        'status',
        'settled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChallengeStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    public static function publicIdPrefix(): string
    {
        return 'RETO';
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'challenger_participant_id');
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function rival(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'rival_participant_id');
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'winner_participant_id');
    }

    /**
     * Los dos retados, siempre en el mismo orden: primero quien retó.
     *
     * @return list<Participant>
     */
    public function contenders(): array
    {
        return [$this->challenger, $this->rival];
    }

    /**
     * Quién va ganando ahora mismo.
     *
     * El criterio es el del deporte: **manda la cantidad de lazos acertados y
     * el menor tiempo acumulado desempata**, cada uno con lo que lleva en su
     * propia categoría del evento. Devuelve `null` mientras ninguno haya
     * acertado un lazo o si los dos van exactamente iguales.
     *
     * Espera `challenger.standings.category` y su gemelo ya cargados.
     */
    public function leader(): ?Participant
    {
        $comparison = $this->compare(
            $this->standingOf($this->challenger),
            $this->standingOf($this->rival),
        );

        return match (true) {
            $comparison < 0 => $this->challenger,
            $comparison > 0 => $this->rival,
            default => null,
        };
    }

    /**
     * Van iguales: mismos aciertos y mismo tiempo, con al menos un lazo
     * acertado. Sin aciertos todavía no hay empate sino nada que comparar.
     */
    public function isTied(): bool
    {
        $challenger = $this->standingOf($this->challenger);
        $rival = $this->standingOf($this->rival);

        return $this->successfulRounds($challenger) > 0
            && $this->compare($challenger, $rival) === 0;
    }

    /**
     * Lo que el vaquero lleva acumulado **en este evento**.
     *
     * Si corre varias categorías del mismo evento manda la inscripción más
     * reciente. Devuelve `null` si todavía no tiene clasificación.
     *
     * Espera `challenger.standings.category` y su gemelo ya cargados.
     */
    public function standingOf(Participant $participant): ?CategoryStanding
    {
        return $participant->standings
            ->filter(fn (CategoryStanding $standing): bool => $standing->category->tournament_id === $this->tournament_id)
            ->sortByDesc('registration_id')
            ->first();
    }

    /**
     * Compara dos acumulados con el orden del reto: negativo si gana el
     * primero, positivo si gana el segundo, cero si van iguales.
     *
     * Los aciertos van antes que el tiempo a propósito: el acumulado solo suma
     * lazos acertados, así que quien falla más lleva menos tiempo y ordenar
     * por tiempo a secas premiaría al que peor corrió.
     */
    private function compare(?CategoryStanding $first, ?CategoryStanding $second): int
    {
        $firstRounds = $this->successfulRounds($first);
        $secondRounds = $this->successfulRounds($second);

        if ($firstRounds !== $secondRounds) {
            return $secondRounds <=> $firstRounds;
        }

        // Ninguno ha acertado un lazo: no hay con qué decidir.
        if ($firstRounds === 0) {
            return 0;
        }

        return $this->timeMs($first) <=> $this->timeMs($second);
    }

    private function successfulRounds(?CategoryStanding $standing): int
    {
        return $standing === null ? 0 : $standing->total_successful_rounds;
    }

    private function timeMs(?CategoryStanding $standing): int
    {
        return $standing === null ? 0 : $standing->total_time_ms;
    }
}
