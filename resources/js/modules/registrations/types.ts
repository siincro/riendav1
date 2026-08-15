export type RegistrationStatus = 'active' | 'withdrawn' | 'disqualified';

/** El acumulado de un inscrito dentro de su categoría. */
export type Standing = {
    position: number | null;
    tied: boolean;
    total_successful_rounds: number;
    total_time_ms: number;
    total_time_formatted: string;
};

/** Una inscripción tal como la expone `RegistrationResource`. */
export type Registration = {
    id: number;
    registration_number: number;
    status: RegistrationStatus;
    status_label: string;
    participant: {
        public_id: string;
        full_name: string;
        city: string | null;
        photo_url: string | null;
    };
    horse: { public_id: string; name: string } | null;
    competition_group?: {
        id: number;
        name: string;
        order: number;
    } | null;
    standing?: Standing | null;
};

/** Jinete disponible para inscribir, con sus caballos activos. */
export type AvailableParticipant = {
    public_id: string;
    full_name: string;
    horses: { public_id: string; name: string }[];
};
