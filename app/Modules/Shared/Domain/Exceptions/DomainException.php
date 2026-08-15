<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Violación de una regla de negocio de RIENDA.
 *
 * Se distingue de una falla técnica: representa una operación que el dominio
 * rechaza (una inscripción duplicada, un resultado en una ronda cerrada) y se
 * traduce a un 422 con un mensaje que el administrador puede entender.
 */
class DomainException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        string $message,
        protected array $errors = [],
        protected string $errorKey = 'domain',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors === [] ? [$this->errorKey => $this->getMessage()] : $this->errors;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => array_map(fn (string $error): array => [$error], $this->errors()),
            ], 422);
        }

        return back()->withInput()->withErrors($this->errors());
    }
}
