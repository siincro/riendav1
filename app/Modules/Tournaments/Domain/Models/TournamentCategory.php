<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Domain\Models;

use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Domain\Enums\CategoryStatus;
use App\Modules\Tournaments\Infrastructure\Database\Factories\TournamentCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Una categoría dentro de un torneo (Profesional, Amateur, Juvenil, Femenina).
 *
 * La categoría es la unidad competitiva real de RIENDA: define su propia
 * estructura de días y rondas, agrupa sus inscripciones y produce su propia
 * clasificación.
 *
 * @property int $id
 * @property int $tournament_id
 * @property string $name
 * @property string $slug
 * @property int $order
 * @property int $number_of_days
 * @property int $rounds_per_day
 * @property string|null $description
 * @property CategoryStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TournamentCategory extends Model
{
    /** @use HasFactory<TournamentCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'slug',
        'description',
        'order',
        'number_of_days',
        'rounds_per_day',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CategoryStatus::class,
            'order' => 'integer',
            'number_of_days' => 'integer',
            'rounds_per_day' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return HasMany<CompetitionDay, $this>
     */
    public function competitionDays(): HasMany
    {
        return $this->hasMany(CompetitionDay::class)->orderBy('day_number');
    }

    /**
     * Alias usado por el enlace de rutas anidadas (`/categories/{category}/days/{day}`).
     *
     * @return HasMany<CompetitionDay, $this>
     */
    public function days(): HasMany
    {
        return $this->competitionDays();
    }

    /**
     * Todas las rondas de la categoría, a través de sus días.
     *
     * Sirve al enlace de rutas anidadas: garantiza en la propia resolución de
     * la URL que una ronda pertenece a esta categoría, y no a otra.
     *
     * @return HasManyThrough<Round, CompetitionDay, $this>
     */
    public function rounds(): HasManyThrough
    {
        return $this->hasManyThrough(Round::class, CompetitionDay::class, 'tournament_category_id', 'competition_day_id');
    }

    /**
     * Todos los resultados de la categoría, a través de sus inscripciones.
     *
     * @return HasManyThrough<RoundResult, Registration, $this>
     */
    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(RoundResult::class, Registration::class, 'tournament_category_id', 'registration_id');
    }

    /**
     * @return HasMany<CategoryStanding, $this>
     */
    public function standings(): HasMany
    {
        return $this->hasMany(CategoryStanding::class);
    }

    /**
     * Total de rondas que debe correr cada inscrito en la categoría.
     *
     * Incluye el lazo final del último día, que es una ronda extra: por eso
     * siempre se suma uno sobre el producto días × rondas por día.
     */
    public function totalRounds(): int
    {
        return ($this->number_of_days * $this->rounds_per_day) + 1;
    }

    /**
     * Cuántos vaqueros entran al lazo final de la categoría.
     */
    public const FINALISTS_LIMIT = 10;

    /**
     * Quiénes clasifican al lazo final: los primeros de la clasificación.
     *
     * La posición sale de la tabla de posiciones, que solo existe para quienes
     * registraron al menos un resultado; quien nunca corrió no clasifica. Con
     * menos de diez con resultado entran todos los que tienen posición.
     *
     * @return Collection<int, int>
     */
    public function finalistRegistrationIds(): Collection
    {
        return $this->standings()
            ->whereNotNull('position')
            ->where('position', '<=', self::FINALISTS_LIMIT)
            ->orderBy('position')
            ->pluck('registration_id');
    }

    public function isFinalist(int $registrationId): bool
    {
        return $this->finalistRegistrationIds()->contains($registrationId);
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function acceptsRegistrations(): bool
    {
        return $this->status->acceptsRegistrations() && ! $this->tournament->isClosed();
    }

    /**
     * La estructura solo puede reconfigurarse mientras no haya empezado a
     * competirse: una vez hay resultados, cambiar días o rondas invalidaría
     * el historial.
     */
    public function structureIsEditable(): bool
    {
        return $this->status === CategoryStatus::Pending;
    }
}
