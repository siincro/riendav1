import type { CompetitionGroup } from '@/modules/tournaments/types';

export type RoundStatus = 'pending' | 'active' | 'finished' | 'cancelled';
export type DayStatus = 'pending' | 'active' | 'finished' | 'cancelled';

export type Round = {
    id: number;
    round_number: number;
    is_final: boolean;
    status: RoundStatus;
    status_label: string;
    accepts_results: boolean;
    results_count: number | null;
    started_at: string | null;
    finished_at: string | null;
};

export type CompetitionDay = {
    id: number;
    day_number: number;
    date: string | null;
    status: DayStatus;
    status_label: string;
    rounds: Round[];
};

/** La ronda sobre la que se está cronometrando. */
export type ActiveRound = {
    id: number;
    round_number: number;
    day_number: number;
    is_final: boolean;
    status: RoundStatus;
    status_label: string;
    accepts_results: boolean;
};

/** El resultado de un inscrito en el lazo activo. */
export type EntryResult = {
    id: number;
    time_ms: number;
    /** Ya formateado por el backend: "8.342". */
    time: string;
    penalty_ms: number;
    /** La penalización, formateada igual que el tiempo. */
    penalty: string;
    /** Lo que suma al acumulado: tiempo + penalización, o cero si falló. */
    total: string;
    successful: boolean;
    notes: string | null;
};

/** Una fila de la consola: un inscrito y su resultado en la ronda activa. */
export type ConsoleEntry = {
    registration_id: number;
    registration_number: number;
    status: string;
    accepts_results: boolean;
    participant: { public_id: string; full_name: string };
    horse: string | null;
    result: EntryResult | null;
};

/** Una fila de la clasificación acumulada. */
export type StandingRow = {
    position: number | null;
    tied: boolean;
    total_successful_rounds: number;
    total_time_ms: number;
    total_time_formatted: string;
    /** Cuánto del acumulado es castigo. */
    total_penalty_ms: number;
    total_penalty_formatted: string;
    recorded_rounds: number;
    registration: {
        id: number;
        registration_number: number;
        status: string;
        participant: { public_id: string; full_name: string };
        horse: string | null;
    };
};

/**
 * Una posición de la clasificación en la cara pública.
 *
 * Igual que `StandingRow` pero sin ids internos: la referencia del jinete es
 * siempre su `public_id`.
 */
export type PublicStandingRow = {
    position: number | null;
    tied: boolean;
    total_successful_rounds: number;
    total_time_ms: number;
    total_time_formatted: string;
    /** Cuánto del acumulado es castigo. */
    total_penalty_ms: number;
    total_penalty_formatted: string;
    recorded_rounds: number;
    registration: {
        registration_number: number;
        participant: { public_id: string; full_name: string };
        horse: string | null;
    };
};

/** Resumen de un día de competencia, a nivel de torneo. */
export type TournamentDaySummary = {
    day_number: number;
    date: string | null;
    status: DayStatus;
    status_label: string;
    rounds_count: number;
};

/** Una posición de la cola de salida del torneo. */
export type TournamentQueueEntry = {
    sequence: number;
    registration_id: number;
    registration_number: number;
    category: { id: number; slug: string; name: string };
    day: {
        id: number;
        day_number: number;
        status: DayStatus;
        status_label: string;
    };
    round: {
        id: number;
        round_number: number;
        is_final: boolean;
        status: RoundStatus;
        status_label: string;
        accepts_results: boolean;
    };
    group: CompetitionGroup | null;
    participant: { public_id: string; full_name: string; photo_url: string | null };
    horse: string | null;
    result: EntryResult | null;
};

/** La clasificación de una categoría dentro de la consola del evento. */
export type TournamentStandings = {
    id: number;
    slug: string;
    name: string;
    status: DayStatus;
    status_label: string;
    standings: StandingRow[];
};

/** Un día de la estructura de una categoría, en la vista pública. */
export type PublicCategoryDay = {
    day_number: number;
    date: string | null;
    status: DayStatus;
    status_label: string;
    rounds: {
        round_number: number;
        is_final: boolean;
        status: RoundStatus;
        status_label: string;
    }[];
};

/** La casilla de un inscrito en un día concreto del cuadro público. */
export type PublicDailyCell = {
    time_ms: number;
    time_formatted: string;
    /** El tiempo ya incluye el castigo; esto es cuánto de él lo es. */
    penalty_ms: number;
    penalty_formatted: string;
    successful_rounds: number;
    recorded_rounds: number;
} | null;

/** Una fila del cuadro diario público: el inscrito y su resultado por día. */
export type PublicDailyRow = {
    registration_number: number;
    participant: { public_id: string; full_name: string };
    horse: string | null;
    per_day: PublicDailyCell[];
};
