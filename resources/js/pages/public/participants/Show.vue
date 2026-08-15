<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import ChallengeCard from '@/modules/challenges/components/ChallengeCard.vue';
import type { Challenge } from '@/modules/challenges/types';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';
import type {
    PublicHistoryEntry,
    PublicHorse,
    PublicParticipant,
} from '@/modules/participants/types';
import LandingHeading from '@/modules/public/components/LandingHeading.vue';
import LandingShell from '@/modules/public/components/LandingShell.vue';
import { formatDate } from '@/modules/shared/format';

/**
 * El perfil público de un jinete, con el mismo cartel que la landing: fondo de
 * cuero, titular de Anton y las cifras en grande. La trayectoria se resume
 * arriba y el historial se lee como la tabla de posiciones.
 */
const props = defineProps<{
    participant: PublicParticipant;
    horses: PublicHorse[];
    history: PublicHistoryEntry[];
    challenges: { data: Challenge[] };
}>();

const origin = computed(
    () =>
        [props.participant.city, props.participant.country]
            .filter(Boolean)
            .join(', ') || 'Colombia',
);

/** Las victorias y los podios salen del propio historial, no de otra consulta. */
const wins = computed(
    () =>
        props.history.filter((entry) => entry.standing?.position === 1).length,
);

const podiums = computed(
    () =>
        props.history.filter(
            (entry) =>
                entry.standing?.position !== null &&
                entry.standing?.position !== undefined &&
                entry.standing.position <= 3,
        ).length,
);

/**
 * Las cifras se cuentan sobre lo que la propia página enseña —el historial
 * visible y la cuadra— para que nunca digan una cosa y la lista de abajo otra.
 */
const metrics = computed(() => [
    { value: String(props.history.length), label: 'Eventos' },
    { value: String(props.horses.length), label: 'Caballos' },
    { value: String(wins.value), label: 'Victorias' },
    { value: String(podiums.value), label: 'Podios' },
]);

/** El oro solo para el primero; el podio en crema y el resto apagado. */
function positionTone(position: number | null): string {
    if (position === 1) {
        return 'text-rienda-dorado';
    }

    if (position !== null && position <= 3) {
        return 'text-rienda-crema';
    }

    return 'text-rienda-sombra';
}
</script>

<template>
    <Head :title="participant.full_name" />

    <LandingShell>
        <!-- Ficha del vaquero -->
        <section
            class="bg-[radial-gradient(120%_90%_at_8%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-noche)_62%)] px-5 py-12 sm:px-8 lg:px-10"
        >
            <nav
                class="mb-7 flex items-center gap-2.5 font-condensed text-sm tracking-[0.16em] text-rienda-sombra uppercase"
            >
                <Link
                    href="/jinetes"
                    class="transition-colors hover:text-rienda-dorado"
                >
                    Vaqueros
                </Link>
                <span>/</span>
                <span class="truncate text-rienda-arena">{{
                    participant.full_name
                }}</span>
            </nav>

            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                <ParticipantAvatar
                    :name="participant.full_name"
                    :photo-url="participant.photo_url"
                    tone="cartel"
                    class="size-24 border border-rienda-crema/12 text-[32px]"
                />

                <div class="min-w-0">
                    <h1
                        class="font-display text-[clamp(2.5rem,5.4vw,4.5rem)] leading-[0.95] text-balance text-rienda-crema uppercase"
                    >
                        {{ participant.full_name }}
                    </h1>
                    <div
                        class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2 font-condensed text-base tracking-[0.12em] text-rienda-arena uppercase"
                    >
                        <span class="flex items-center gap-1.5">
                            <MapPin class="size-4 shrink-0" />
                            {{ origin }}
                        </span>
                        <span class="text-rienda-sombra">{{
                            participant.public_id
                        }}</span>
                        <span
                            class="rounded-full border px-3 py-1 text-sm tracking-[0.14em]"
                            :class="
                                participant.status === 'active'
                                    ? 'border-rienda-fuego/70 text-rienda-dorado'
                                    : 'border-rienda-crema/20 text-rienda-arena'
                            "
                        >
                            {{ participant.status_label }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <div
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="rounded-2xl border border-rienda-crema/8 bg-rienda-tierra px-4.5 py-4"
                >
                    <p class="font-display text-[28px] text-rienda-crema">
                        {{ metric.value }}
                    </p>
                    <p
                        class="font-condensed text-sm tracking-[0.14em] text-rienda-arena uppercase"
                    >
                        {{ metric.label }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Caballos -->
        <section class="bg-rienda-carbon px-5 py-14 sm:px-8 lg:px-10">
            <LandingHeading kicker="En la cuadra" title="Sus caballos" />

            <p
                v-if="horses.length === 0"
                class="mt-7 rounded-[20px] border border-dashed border-rienda-crema/15 p-10 text-center text-sm text-rienda-arena"
            >
                Este vaquero todavía no ha registrado caballos en RIENDA.
            </p>

            <div
                v-else
                class="mt-7 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="horse in horses"
                    :key="horse.name"
                    class="rounded-[20px] border border-rienda-crema/8 bg-rienda-tierra p-5"
                >
                    <p
                        class="truncate font-display text-[22px] text-rienda-crema uppercase"
                    >
                        {{ horse.name }}
                    </p>
                    <p
                        class="truncate font-condensed text-base tracking-[0.1em] text-rienda-arena uppercase"
                    >
                        {{
                            [horse.breed, horse.color]
                                .filter(Boolean)
                                .join(' · ') || 'Sin ficha'
                        }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Retos -->
        <section
            v-if="challenges.data.length > 0"
            class="bg-rienda-noche px-5 pt-14 sm:px-8 lg:px-10"
        >
            <div class="flex flex-wrap items-end justify-between gap-5">
                <LandingHeading kicker="Cara a cara" title="Sus retos" />
                <p
                    class="max-w-[36ch] text-base leading-relaxed text-rienda-arena"
                >
                    Gana quien más lazos acierte en su categoría; el menor
                    tiempo acumulado desempata.
                </p>
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <ChallengeCard
                    v-for="challenge in challenges.data"
                    :key="challenge.public_id"
                    :challenge="challenge"
                    tone="cartel"
                />
            </div>
        </section>

        <!-- Historial deportivo -->
        <section class="bg-rienda-noche px-5 py-14 sm:px-8 lg:px-10">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <LandingHeading
                    kicker="Trayectoria"
                    title="Historial deportivo"
                />
                <p
                    class="max-w-[36ch] text-base leading-relaxed text-rienda-arena"
                >
                    Participaciones en eventos publicados, con la posición final
                    de cada categoría.
                </p>
            </div>

            <p
                v-if="history.length === 0"
                class="mt-7 rounded-[20px] border border-dashed border-rienda-crema/15 p-10 text-center text-sm text-rienda-arena"
            >
                Cuando compita en eventos, aquí se acumulará su historial.
            </p>

            <div v-else class="mt-7 flex flex-col gap-3">
                <div
                    v-for="entry in history"
                    :key="`${entry.tournament.slug}-${entry.category.slug}`"
                    class="flex flex-col gap-4 rounded-[18px] border border-rienda-crema/8 bg-rienda-tierra px-5 py-5 transition-colors hover:bg-rienda-cuero sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <Link
                                :href="`/torneos/${entry.tournament.slug}`"
                                class="font-display text-[21px] text-rienda-crema uppercase transition-colors hover:text-rienda-dorado"
                            >
                                {{ entry.tournament.name }}
                            </Link>
                            <span
                                class="rounded-full border border-rienda-crema/20 px-3 py-1 font-condensed text-sm tracking-[0.14em] text-rienda-arena uppercase"
                            >
                                {{ entry.tournament.status_label }}
                            </span>
                        </div>
                        <p
                            class="mt-1 font-condensed text-base tracking-[0.08em] text-rienda-arena uppercase"
                        >
                            {{ entry.category.name }} ·
                            {{ formatDate(entry.tournament.start_date) }} ·
                            dorsal
                            {{ entry.registration_number }}
                            <template v-if="entry.horse">
                                · {{ entry.horse }}</template
                            >
                        </p>
                    </div>

                    <div
                        v-if="entry.standing"
                        class="flex shrink-0 items-end gap-6 sm:gap-8"
                    >
                        <div>
                            <p
                                class="font-display text-[34px] leading-none tabular-nums"
                                :class="positionTone(entry.standing.position)"
                            >
                                {{
                                    entry.standing.position
                                        ? `#${entry.standing.position}`
                                        : '—'
                                }}
                            </p>
                            <p
                                class="font-condensed text-sm tracking-[0.16em] text-rienda-sombra uppercase"
                            >
                                Posición
                            </p>
                        </div>
                        <div>
                            <p
                                class="font-display text-[26px] leading-none text-rienda-crema tabular-nums"
                            >
                                {{ entry.standing.total_successful_rounds }}
                            </p>
                            <p
                                class="font-condensed text-sm tracking-[0.16em] text-rienda-sombra uppercase"
                            >
                                Aciertos
                            </p>
                        </div>
                        <div>
                            <p
                                class="font-display text-[26px] leading-none text-rienda-dorado tabular-nums"
                            >
                                {{ entry.standing.total_time_formatted }}
                            </p>
                            <p
                                class="font-condensed text-sm tracking-[0.16em] text-rienda-sombra uppercase"
                            >
                                Tiempo
                            </p>
                        </div>
                    </div>

                    <p
                        v-else
                        class="flex shrink-0 items-center gap-2 font-condensed text-base tracking-[0.14em] text-rienda-sombra uppercase"
                    >
                        <Trophy class="size-4" />
                        Sin clasificación
                    </p>
                </div>
            </div>
        </section>
    </LandingShell>
</template>
