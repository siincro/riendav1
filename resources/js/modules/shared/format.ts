/**
 * Formato de los datos de competencia.
 *
 * El backend almacena los tiempos como enteros en milisegundos; la interfaz
 * los muestra siempre en segundos con tres decimales.
 */

/** 8342 → "8.342" */
export function formatTime(milliseconds: number): string {
    const seconds = Math.floor(milliseconds / 1000);
    const remainder = milliseconds % 1000;

    return `${seconds}.${String(remainder).padStart(3, '0')}`;
}

/** 8342 → "08.342" — con relleno, para pantallas de transmisión. */
export function formatTimeForBroadcast(milliseconds: number): string {
    const seconds = Math.floor(milliseconds / 1000);
    const remainder = milliseconds % 1000;

    return `${String(seconds).padStart(2, '0')}.${String(remainder).padStart(3, '0')}`;
}

/**
 * "8,342" o "8.34" → "8.342"
 *
 * Normaliza lo que el administrador escribe mientras cronometra, sin
 * bloquear la escritura a medias.
 */
export function normalizeTimeInput(value: string): string {
    return value.replace(',', '.').replace(/[^\d.]/g, '');
}

/** Cuántos días cubre un torneo, contando el primero y el último. */
export function dayCount(start: string | null, end: string | null): string {
    if (start === null) {
        return '—';
    }

    if (end === null || end === start) {
        return '1';
    }

    const span =
        new Date(`${end}T00:00:00`).getTime() -
        new Date(`${start}T00:00:00`).getTime();

    return String(Math.round(span / 86_400_000) + 1);
}

/** «1 torneo», «3 torneos»: la interfaz nunca escribe «1 torneos». */
export function plural(
    count: number,
    singular: string,
    pluralForm: string,
): string {
    return `${count} ${count === 1 ? singular : pluralForm}`;
}

/** "2026-08-20" → "20 ago 2026" */
export function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/** "Lazo 3" o "Lazo Final": cómo se nombra una ronda en la interfaz. */
export function roundLabel(round: {
    round_number: number;
    is_final: boolean;
}): string {
    return round.is_final ? 'Lazo Final' : `Lazo ${round.round_number}`;
}

/** Rango de fechas de un torneo. */
export function formatDateRange(
    start: string | null,
    end: string | null,
): string {
    if (!start && !end) {
        return 'Fechas por definir';
    }

    if (!end || start === end) {
        return formatDate(start);
    }

    return `${formatDate(start)} — ${formatDate(end)}`;
}
