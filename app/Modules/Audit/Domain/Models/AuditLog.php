<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una entrada del registro de auditoría.
 *
 * Es inmutable por diseño: solo se crea, nunca se actualiza. De ahí que no
 * tenga `updated_at`.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $entity
 * @property int $entity_id
 * @property array<string, mixed>|null $old_value
 * @property array<string, mixed>|null $new_value
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'entity',
        'entity_id',
        'old_value',
        'new_value',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
