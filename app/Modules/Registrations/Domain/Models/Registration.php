<?php

declare(strict_types=1);

namespace App\Modules\Registrations\Domain\Models;

use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Leaderboards\Domain\Models\DailyResult;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Domain\Enums\RegistrationStatus;
use App\Modules\Registrations\Infrastructure\Database\Factories\RegistrationFactory;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * La participación de un jinete en una categoría concreta de un torneo.
 *
 * Es la bisagra del modelo: conecta al participante permanente con una
 * competencia puntual y fija el caballo con el que compite allí. Todos los
 * resultados cuelgan de la inscripción, no del participante.
 *
 * @property int $id
 * @property int $tournament_category_id
 * @property int $participant_id
 * @property int|null $horse_id
 * @property int|null $competition_group_id
 * @property int $registration_number
 * @property RegistrationStatus $status
 */
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_category_id',
        'participant_id',
        'horse_id',
        'competition_group_id',
        'registration_number',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'registration_number' => 'integer',
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
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return BelongsTo<Horse, $this>
     */
    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    /**
     * @return BelongsTo<CompetitionGroup, $this>
     */
    public function competitionGroup(): BelongsTo
    {
        return $this->belongsTo(CompetitionGroup::class);
    }

    /**
     * @return HasMany<RoundResult, $this>
     */
    public function roundResults(): HasMany
    {
        return $this->hasMany(RoundResult::class);
    }

    /**
     * @return HasMany<DailyResult, $this>
     */
    public function dailyResults(): HasMany
    {
        return $this->hasMany(DailyResult::class);
    }

    /**
     * @return HasOne<CategoryStanding, $this>
     */
    public function standing(): HasOne
    {
        return $this->hasOne(CategoryStanding::class);
    }

    /**
     * La clasificación acumulada de esta inscripción, si ya tiene alguna.
     *
     * La relación no existe hasta que se registra el primer resultado, y
     * tampoco está cargada si nadie la pidió: quien lea el acumulado debe
     * contar con que llegue vacía.
     */
    public function currentStanding(): ?CategoryStanding
    {
        return $this->standing;
    }

    public function acceptsResults(): bool
    {
        return $this->status->acceptsResults();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), RegistrationStatus::Active);
    }
}
