import type {
    CategoryStatus,
    PublicTournament,
} from '@/modules/tournaments/types';

/**
 * Los tintes del cartel.
 *
 * En la cara pública el estado no se dice con una insignia gris: se tiñe. Un
 * torneo en pista arde en brasa, uno publicado espera en fuego y lo demás se
 * apaga en arena. Vive aquí para que la landing, el calendario y el directorio
 * de torneos usen exactamente el mismo criterio.
 */
export function tournamentTone(tournament: PublicTournament): string {
    if (tournament.is_live) {
        return 'border-rienda-brasa text-rienda-ascua';
    }

    if (tournament.status === 'published') {
        return 'border-rienda-fuego/70 text-rienda-dorado';
    }

    return 'border-rienda-crema/20 text-rienda-arena';
}

/** El mismo criterio para la categoría: arde la que está en pista. */
export function categoryTone(status: CategoryStatus): string {
    if (status === 'in_progress') {
        return 'border-rienda-brasa text-rienda-ascua';
    }

    if (status === 'pending') {
        return 'border-rienda-fuego/70 text-rienda-dorado';
    }

    return 'border-rienda-crema/20 text-rienda-arena';
}
