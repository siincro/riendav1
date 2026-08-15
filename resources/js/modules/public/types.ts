import type { PublicStandingRow } from '@/modules/competition/types';
import type { PublicParticipant } from '@/modules/participants/types';
import type { CategoryStatus } from '@/modules/tournaments/types';

/** Una categoría del torneo en vivo, tal como la pinta la landing. */
export type LandingCategory = {
    slug: string;
    name: string;
    status: CategoryStatus;
    status_label: string;
    registrations_count: number | null;
};

/**
 * El marcador de una categoría en pista: dónde va la competencia y la
 * clasificación acumulada hasta la última manga cronometrada.
 */
export type LandingBoard = {
    category: LandingCategory;
    day: { day_number: number; date: string | null } | null;
    round: { round_number: number; is_final: boolean } | null;
    standings: { data: PublicStandingRow[] };
};

/**
 * El bloque «en vivo» de la landing: el torneo en curso, todas sus categorías
 * y un marcador por cada una de las que están corriendo.
 */
export type LandingActiveBlock = {
    tournament: {
        public_id: string;
        name: string;
        slug: string;
        location: string | null;
        start_date: string | null;
        end_date: string | null;
    };
    categories: LandingCategory[];
    boards: LandingBoard[];
};

/** Un tiempo recién cronometrado, con el torneo y la ronda de donde viene. */
export type LandingResult = {
    id: number;
    time_formatted: string;
    successful: boolean;
    created_at: string | null;
    participant: { public_id: string; full_name: string };
    horse: string | null;
    tournament: { slug: string; name: string };
    category: { slug: string; name: string };
    day_number: number;
    round_number: number;
};

/** Un jinete destacado: el perfil público más su cuenta de victorias. */
export type LandingRider = PublicParticipant & { wins_count: number };
