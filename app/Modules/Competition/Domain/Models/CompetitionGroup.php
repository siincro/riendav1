<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Models;

use App\Modules\Competition\Infrastructure\Database\Factories\CompetitionGroupFactory;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un grupo de salida dentro de un torneo.
 *
 * Los grupos ordenan la operación en pista: no cambian la clasificación, solo
 * definen en qué momento sale cada vaquero a competir. Un torneo puede mezclar
 * categorías dentro de un mismo grupo cuando la logística lo requiere.
 *
 * @property int $id
 * @property int $tournament_id
 * @property string $name
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CompetitionGroup extends Model
{
    /** @use HasFactory<CompetitionGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
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
}
