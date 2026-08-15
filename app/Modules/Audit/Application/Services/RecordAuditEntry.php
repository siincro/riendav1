<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Domain\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Deja constancia de una operación sobre información oficial.
 */
final class RecordAuditEntry
{
    /**
     * @param  array<string, mixed>|null  $oldValue
     * @param  array<string, mixed>|null  $newValue
     * @param  array<string, mixed>  $context
     */
    public function handle(
        string $action,
        Model $entity,
        ?array $oldValue = null,
        ?array $newValue = null,
        array $context = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity' => class_basename($entity),
            'entity_id' => $entity->getKey(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'context' => $context === [] ? null : $context,
        ]);
    }
}
