<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Representa una duración de competencia en milisegundos.
 *
 * RIENDA nunca almacena tiempos como FLOAT: la unidad canónica es el entero
 * en milisegundos. Este value object es el único punto donde se traduce
 * entre la representación humana ("8.342") y la representación persistida (8342).
 */
final readonly class Milliseconds implements JsonSerializable, Stringable
{
    public const int MAX = 3_600_000; // 1 hora: techo defensivo para un tiempo de ronda.

    private function __construct(public int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('El tiempo no puede ser negativo.');
        }

        if ($value > self::MAX) {
            throw new InvalidArgumentException('El tiempo excede el máximo permitido.');
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromInt(int $milliseconds): self
    {
        return new self($milliseconds);
    }

    /**
     * Construye desde la representación en segundos que usa la interfaz.
     *
     * Acepta "8.342", "8,342", "8.34", "8" y valores numéricos.
     * La conversión se hace sobre la cadena — nunca con aritmética de punto
     * flotante — para evitar que 8.342 se convierta en 8341.999999.
     */
    public static function fromSeconds(string|int|float $seconds): self
    {
        $normalized = str_replace(',', '.', trim((string) $seconds));

        if ($normalized === '') {
            return self::zero();
        }

        if (preg_match('/^\d{1,4}(\.\d{1,3})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException(
                'Formato de tiempo inválido. Use segundos con hasta tres decimales, por ejemplo 8.342.'
            );
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');

        return new self(((int) $whole * 1000) + (int) str_pad($fraction, 3, '0'));
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }

    /**
     * Representación humana en segundos: 8342 => "8.342".
     */
    public function format(): string
    {
        return sprintf('%d.%03d', intdiv($this->value, 1000), $this->value % 1000);
    }

    /**
     * Representación con relleno a dos dígitos usada por RIENDA TV: 8342 => "08.342".
     */
    public function formatForBroadcast(): string
    {
        return sprintf('%02d.%03d', intdiv($this->value, 1000), $this->value % 1000);
    }

    public function jsonSerialize(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
