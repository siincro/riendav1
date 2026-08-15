<?php

declare(strict_types=1);

namespace App\Modules\Horses\Domain\Models;

use App\Modules\Horses\Domain\Enums\HorseSex;
use App\Modules\Horses\Domain\Enums\HorseStatus;
use App\Modules\Horses\Infrastructure\Database\Factories\HorseFactory;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Un caballo registrado en RIENDA.
 *
 * Igual que el participante, el caballo es una entidad permanente e
 * independiente del torneo. Un jinete puede tener varios caballos y competir
 * con distintos caballos en distintos torneos: es la inscripción la que
 * determina con cuál compite en cada competencia.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property int|null $participant_id
 * @property string|null $owner
 * @property string|null $breed
 * @property string|null $color
 * @property string|null $photo_path
 * @property HorseStatus $status
 * @property HorseSex|null $sex
 * @property Carbon|null $birth_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $photo_url
 */
class Horse extends Model
{
    /** @use HasFactory<HorseFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'participant_id',
        'name',
        'owner',
        'breed',
        'color',
        'sex',
        'birth_date',
        'photo_path',
        'status',
    ];

    protected $appends = ['photo_url'];

    public static function publicIdPrefix(): string
    {
        return 'H';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HorseStatus::class,
            'sex' => HorseSex::class,
            'birth_date' => 'date',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path === null ? null : Storage::url($this->photo_path);
    }

    /**
     * El jinete al que está asociado el caballo dentro de RIENDA.
     *
     * Es opcional: `owner` guarda el nombre del propietario legal cuando este
     * no es un participante registrado en la plataforma.
     *
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function canCompete(): bool
    {
        return $this->status->canCompete();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', HorseStatus::Active);
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
                ->orWhere('public_id', 'like', $like)
                ->orWhere('owner', 'like', $like);
        });
    }
}
