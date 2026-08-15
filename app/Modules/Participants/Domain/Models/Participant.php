<?php

declare(strict_types=1);

namespace App\Modules\Participants\Domain\Models;

use App\Models\User;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Participants\Domain\Enums\ParticipantStatus;
use App\Modules\Participants\Infrastructure\Database\Factories\ParticipantFactory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Un jinete de lazo.
 *
 * El participante es la entidad permanente del sistema: no pertenece a ningún
 * torneo, los torneos solamente registran su participación. Todo el historial
 * deportivo se acumula sobre este registro.
 *
 * @property int $id
 * @property string $public_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $document
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $country
 * @property string|null $photo_path
 * @property ParticipantStatus $status
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 * @property-read string|null $photo_url
 */
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'first_name',
        'last_name',
        'document',
        'phone',
        'city',
        'country',
        'photo_path',
        'status',
        'user_id',
    ];

    protected $appends = ['full_name', 'photo_url'];

    public static function publicIdPrefix(): string
    {
        return 'RND';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParticipantStatus::class,
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path === null ? null : Storage::url($this->photo_path);
    }

    /**
     * La cuenta que reclamó este perfil, si existe.
     *
     * Un participante puede vivir en RIENDA sin cuenta de usuario: en el MVP
     * los registra exclusivamente un administrador.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Horse, $this>
     */
    public function horses(): HasMany
    {
        return $this->hasMany(Horse::class);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * La clasificación del vaquero en cada categoría que ha corrido.
     *
     * @return HasManyThrough<CategoryStanding, Registration, $this>
     */
    public function standings(): HasManyThrough
    {
        return $this->hasManyThrough(
            CategoryStanding::class,
            Registration::class,
            'participant_id',
            'registration_id',
        );
    }

    /**
     * «Su categoría»: la de la inscripción más reciente, que es donde el
     * vaquero está corriendo ahora. Devuelve `null` si todavía no tiene
     * ningún acumulado.
     *
     * Lee la relación ya cargada cuando la hay, para no consultar por fila.
     */
    public function currentStanding(): ?CategoryStanding
    {
        return $this->standings
            ->sortByDesc('registration_id')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === ParticipantStatus::Active;
    }

    public function canBeRegistered(): bool
    {
        return $this->status->canBeRegistered();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ParticipantStatus::Active);
    }

    /**
     * Búsqueda de la cara pública: por nombre o identificador público, nunca
     * por el documento.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearchPublic(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace('%', '\%', $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('public_id', 'like', $like);
        });
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
            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('document', 'like', $like)
                ->orWhere('public_id', 'like', $like);
        });
    }
}
