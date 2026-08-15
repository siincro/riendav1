<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Domain\Models;

use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Infrastructure\Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Una competencia completa.
 *
 * El torneo agrupa categorías; no es dueño de participantes ni de caballos,
 * solamente registra su participación a través de las inscripciones.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $location
 * @property string|null $cover_image_path
 * @property TournamentStatus $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $cover_image_url
 */
class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'location',
        'start_date',
        'end_date',
        'status',
        'cover_image_path',
    ];

    protected $appends = ['cover_image_url'];

    public static function publicIdPrefix(): string
    {
        return 'T';
    }

    /**
     * El identificador del torneo lleva el año de la competencia: T-2026-001.
     */
    public function generatePublicId(): string
    {
        $year = ($this->start_date ?? now())->format('Y');
        $prefix = self::publicIdPrefix().'-'.$year.'-';

        return $prefix.str_pad((string) $this->nextPublicIdSequence($prefix), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TournamentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image_path === null ? null : Storage::url($this->cover_image_path);
    }

    /**
     * @return HasMany<TournamentCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(TournamentCategory::class)->orderBy('order')->orderBy('name');
    }

    /**
     * Todas las inscripciones del torneo, a través de sus categorías.
     *
     * Permite contar cuántos jinetes compiten en el torneo sin exponer el
     * id interno de las categorías intermedias.
     *
     * @return HasManyThrough<Registration, TournamentCategory, $this>
     */
    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, TournamentCategory::class, 'tournament_id', 'tournament_category_id');
    }

    /**
     * Los grupos de salida que ordenan la operación de pista del evento.
     *
     * @return HasMany<CompetitionGroup, $this>
     */
    public function competitionGroups(): HasMany
    {
        return $this->hasMany(CompetitionGroup::class)->orderBy('order')->orderBy('name');
    }

    /**
     * Alias para la resolución de rutas anidadas (`tournaments/{tournament}/groups/{group}`).
     *
     * @return HasMany<CompetitionGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->competitionGroups();
    }

    public function isLive(): bool
    {
        return $this->status->isLive();
    }

    /**
     * Un torneo solo se puede borrar mientras esté vacío.
     *
     * Sin inscritos no hay historial deportivo que perder: lo que cuelga de él
     * —categorías, días y rondas— es andamiaje que se rehace en un minuto. En
     * cuanto hay un jinete inscrito, el torneo es información oficial y se
     * cancela, no se borra.
     *
     * Usa el conteo ya cargado (`withCount('registrations')`) cuando lo haya,
     * para no disparar una consulta por fila en los listados.
     */
    public function canBeDeleted(): bool
    {
        return ($this->registrations_count ?? $this->registrations()->count()) === 0;
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TournamentStatus::Published->value,
            TournamentStatus::InProgress->value,
            TournamentStatus::Finished->value,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace('%', '\%', $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhere('public_id', 'like', $like);
        });
    }
}
