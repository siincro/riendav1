<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Modules\Shared\Domain\ValueObjects\Milliseconds;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MillisecondsTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function secondsProvider(): array
    {
        return [
            'tres decimales' => ['8.342', 8342],
            'ceros intermedios' => ['12.005', 12005],
            'dos decimales' => ['9.11', 9110],
            'un decimal' => ['7.5', 7500],
            'entero' => ['10', 10000],
            'coma decimal' => ['8,342', 8342],
            'cero' => ['0', 0],
            'espacios' => ['  8.342  ', 8342],
        ];
    }

    #[Test]
    #[DataProvider('secondsProvider')]
    public function convierte_segundos_a_milisegundos_sin_perder_precision(string $input, int $expected): void
    {
        $this->assertSame($expected, Milliseconds::fromSeconds($input)->value);
    }

    #[Test]
    public function la_conversion_no_arrastra_error_de_punto_flotante(): void
    {
        // Con aritmética de float, 8.342 * 1000 puede dar 8341.999999999999.
        foreach (range(1, 999) as $thousandths) {
            $seconds = sprintf('8.%03d', $thousandths);

            $this->assertSame(
                8000 + $thousandths,
                Milliseconds::fromSeconds($seconds)->value,
                "Falló la conversión de {$seconds}"
            );
        }
    }

    #[Test]
    public function formatea_milisegundos_como_segundos(): void
    {
        $this->assertSame('8.342', Milliseconds::fromInt(8342)->format());
        $this->assertSame('12.005', Milliseconds::fromInt(12005)->format());
        $this->assertSame('0.000', Milliseconds::zero()->format());
        $this->assertSame('76.208', Milliseconds::fromInt(76208)->format());
    }

    #[Test]
    public function formatea_con_relleno_para_transmision(): void
    {
        $this->assertSame('08.342', Milliseconds::fromInt(8342)->formatForBroadcast());
    }

    #[Test]
    public function la_ida_y_vuelta_es_estable(): void
    {
        $original = '9.112';

        $this->assertSame($original, Milliseconds::fromSeconds($original)->format());
    }

    #[Test]
    public function suma_tiempos(): void
    {
        $total = Milliseconds::fromSeconds('8.342')
            ->add(Milliseconds::fromSeconds('7.521'))
            ->add(Milliseconds::fromSeconds('9.112'));

        $this->assertSame(24975, $total->value);
        $this->assertSame('24.975', $total->format());
    }

    #[Test]
    public function rechaza_formatos_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Milliseconds::fromSeconds('8.3421');
    }

    #[Test]
    public function rechaza_texto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Milliseconds::fromSeconds('ocho segundos');
    }

    #[Test]
    public function rechaza_valores_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Milliseconds::fromInt(-1);
    }
}
