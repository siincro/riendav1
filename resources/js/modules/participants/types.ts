import type { ActiveStatus } from '@/modules/shared/types';

/**
 * Un jinete tal como lo expone `ParticipantResource`.
 *
 * El identificador interno nunca viaja al frontend: toda referencia usa
 * `public_id` (RND-000184).
 */
export type Participant = {
    public_id: string;
    first_name: string;
    last_name: string;
    full_name: string;
    document: string | null;
    phone: string | null;
    city: string | null;
    country: string | null;
    photo_url: string | null;
    status: ActiveStatus;
    status_label: string;
    horses_count?: number;
    registrations_count?: number;
    created_at: string | null;
};

/** Resumen de un caballo dentro del perfil de un jinete. */
export type ParticipantHorse = {
    public_id: string;
    name: string;
    status: ActiveStatus;
};

/**
 * Un jinete en la cara pública.
 *
 * Igual que `Participant` pero sin datos sensibles: no viajan documento ni
 * teléfono, y el identificador público es la única referencia.
 */
export type PublicParticipant = {
    public_id: string;
    first_name: string;
    last_name: string;
    full_name: string;
    city: string | null;
    country: string | null;
    photo_url: string | null;
    status: ActiveStatus;
    status_label: string;
    horses_count?: number;
    registrations_count?: number;
};

/** Un caballo del perfil público de un jinete. */
export type PublicHorse = {
    name: string;
    breed: string | null;
    color: string | null;
};

/** La posición final de un inscrito en una categoría. */
export type PublicHistoryStanding = {
    position: number | null;
    total_successful_rounds: number;
    total_time_formatted: string;
};

/** Una participación del historial deportivo público de un jinete. */
export type PublicHistoryEntry = {
    tournament: {
        public_id: string;
        name: string;
        slug: string;
        start_date: string | null;
        status: string;
        status_label: string;
    };
    category: {
        slug: string;
        name: string;
    };
    registration_number: number;
    horse: string | null;
    standing: PublicHistoryStanding | null;
};
