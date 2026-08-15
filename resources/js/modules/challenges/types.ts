/** El estado de un reto entre dos vaqueros. */
export type ChallengeStatus = 'open' | 'settled' | 'cancelled';

/** Uno de los dos retados, con lo que lleva acumulado en su categoría. */
export type ChallengeContender = {
    participant: {
        public_id: string;
        full_name: string;
        photo_url: string | null;
    };
    /** La categoría en la que se le mide dentro del evento; nula si no corre ninguna. */
    category: { slug: string; name: string } | null;
    total_time_ms: number;
    total_time_formatted: string | null;
    total_successful_rounds: number;
    /** Va ganando si el reto sigue en juego; ganó si ya está resuelto. */
    is_leader: boolean;
};

/**
 * Un reto entre dos vaqueros de un mismo evento, corran la categoría que
 * corran: a cada uno se le mide con lo suyo. `contenders` llega siempre con
 * dos, retador primero.
 */
export type Challenge = {
    public_id: string;
    tournament: { slug: string; name: string };
    status: ChallengeStatus;
    status_label: string;
    is_tied: boolean;
    settled_at: string | null;
    contenders: ChallengeContender[];
};

/** Un vaquero al que se puede retar dentro de un evento. */
export type ChallengeCandidate = {
    public_id: string;
    full_name: string;
};

/** Un evento con los vaqueros que corren en él. */
export type ChallengeableEvent = {
    slug: string;
    name: string;
    contenders: ChallengeCandidate[];
};
