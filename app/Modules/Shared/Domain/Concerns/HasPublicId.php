<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Concerns;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * Asigna un identificador público permanente y no enumerable de forma trivial.
 *
 * El `id` interno nunca se expone; toda referencia pública usa `public_id`
 * (RND-000184, H-000421, T-2026-001).
 */
trait HasPublicId
{
    /**
     * Prefijo del identificador público del modelo. Ej.: "RND".
     */
    abstract public static function publicIdPrefix(): string;

    /**
     * Cantidad de dígitos del consecutivo. Ej.: 6 => 000184.
     */
    public static function publicIdPadding(): int
    {
        return 6;
    }

    public static function bootHasPublicId(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->public_id)) {
                $model->public_id = $model->generatePublicId();
            }
        });
    }

    public function generatePublicId(): string
    {
        $prefix = static::publicIdPrefix().'-';

        return $prefix.str_pad(
            (string) $this->nextPublicIdSequence($prefix),
            static::publicIdPadding(),
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Siguiente consecutivo disponible para el prefijo dado.
     *
     * Se calcula sobre la propia tabla para que el identificador sea denso y
     * legible. La unicidad final la garantiza el índice único de la columna.
     */
    protected function nextPublicIdSequence(string $prefix): int
    {
        $length = strlen($prefix);

        $last = static::query()
            ->withoutGlobalScopes()
            ->where('public_id', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(public_id) desc')
            ->orderBy('public_id', 'desc')
            ->value('public_id');

        if ($last === null) {
            return 1;
        }

        return ((int) substr($last, $length)) + 1;
    }

    /**
     * Reintenta la inserción si dos procesos calcularon el mismo consecutivo.
     *
     * La inserción de un modelo ya es atómica: lo único que hace falta es
     * volver a intentarlo cuando el índice único de `public_id` rechaza una
     * colisión por concurrencia.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function withPublicIdRetries(Closure $callback, int $attempts = 5): mixed
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $callback();
            } catch (UniqueConstraintViolationException $exception) {
                if (! str_contains($exception->getMessage(), 'public_id') || $attempt === $attempts) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('No fue posible generar un identificador público único.');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
