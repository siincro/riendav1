import type { ActiveStatus } from '@/modules/shared/types';

/** Un caballo tal como lo expone `HorseResource`. */
export type Horse = {
    public_id: string;
    name: string;
    owner: string | null;
    breed: string | null;
    color: string | null;
    sex: string | null;
    sex_label: string | null;
    birth_date: string | null;
    photo_url: string | null;
    status: ActiveStatus;
    status_label: string;
    participant?: { public_id: string; full_name: string } | null;
    created_at: string | null;
};

/** Jinete disponible para asociar un caballo. */
export type HorseOwnerOption = {
    public_id: string;
    full_name: string;
};
