import type { Option } from '@/modules/shared/types';

export type TournamentStatus =
    | 'draft'
    | 'published'
    | 'in_progress'
    | 'finished'
    | 'cancelled';

export type CategoryStatus = 'pending' | 'in_progress' | 'finished' | 'cancelled';

/** Un grupo de salida dentro del evento. */
export type CompetitionGroup = {
    id: number;
    name: string;
    order: number;
    registrations_count?: number;
};

/** Una categoría tal como la expone `TournamentCategoryResource`. */
export type TournamentCategory = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    order: number;
    number_of_days: number;
    rounds_per_day: number;
    total_rounds: number;
    status: CategoryStatus;
    status_label: string;
    structure_is_editable: boolean;
    accepts_registrations: boolean;
    registrations_count?: number;
    tournament?: {
        slug: string;
        name: string;
        public_id: string;
        status: TournamentStatus;
    };
};

/** Un torneo tal como lo expone `TournamentResource`. */
export type Tournament = {
    public_id: string;
    name: string;
    slug: string;
    description: string | null;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    status: TournamentStatus;
    status_label: string;
    is_live: boolean;
    cover_image_url: string | null;
    /** Estados a los que el torneo puede pasar desde el actual. */
    allowed_transitions: Option[];
    /** Falso en cuanto el torneo tiene un jinete inscrito. */
    can_be_deleted: boolean;
    categories_count?: number;
    registrations_count?: number;
    categories?: TournamentCategory[];
    competition_groups?: CompetitionGroup[];
};

/** Un torneo en la cara pública, sin datos de administración. */
export type PublicTournament = {
    public_id: string;
    name: string;
    slug: string;
    description: string | null;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    status: TournamentStatus;
    status_label: string;
    is_live: boolean;
    cover_image_url: string | null;
    categories_count?: number;
    registrations_count?: number;
    categories?: PublicTournamentCategory[];
};

/** Una categoría de un torneo tal como la ve el público. */
export type PublicTournamentCategory = {
    slug: string;
    name: string;
    description: string | null;
    order: number;
    number_of_days: number;
    rounds_per_day: number;
    total_rounds: number;
    status: CategoryStatus;
    status_label: string;
    registrations_count?: number;
    tournament?: {
        slug: string;
        name: string;
        public_id: string;
        status: TournamentStatus;
    };
};
