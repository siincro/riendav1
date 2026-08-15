<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import echo from '@/echo';
import ChallengeCard from '@/modules/challenges/components/ChallengeCard.vue';
import type { Challenge } from '@/modules/challenges/types';
import type {
    PublicCategoryDay,
    PublicDailyRow,
    PublicStandingRow,
} from '@/modules/competition/types';
import LandingBreadcrumb from '@/modules/public/components/LandingBreadcrumb.vue';
import LandingHeading from '@/modules/public/components/LandingHeading.vue';
import LandingShell from '@/modules/public/components/LandingShell.vue';
import { categoryTone } from '@/modules/public/tones';
import { formatDate, plural } from '@/modules/shared/format';
import type { PublicTournamentCategory } from '@/modules/tournaments/types';

/**
 * La clasificación pública de una categoría: la general acumulada y el detalle
 * día a día, con el cartel de la landing. El oro es del primer puesto.
 */
const props = defineProps<{
    category: PublicTournamentCategory;
    standings: { data: PublicStandingRow[] };
    days: PublicCategoryDay[];
    rows: PublicDailyRow[];
    challenges: { data: Challenge[] };
}>();

/**
 * Tiempo real: la categoría escucha su propio canal y se recarga con cada lazo
 * cronometrado, así que la clasificación y los retos que cuelgan de ella se
 * mueven solos mientras la manga está en pista.
 *
 * El `.` inicial evita que Echo anteponga el namespace `App\Events\...`.
 */
const LIVE_EVENTS = [
    '.round-result.recorded',
    '.round-result.updated',
    '.round.started',
    '.round.completed',
    '.competition-day.started',
    '.competition-day.completed',
] as const;

const liveChannelName = computed(() =>
    props.category.tournament
        ? `public-category.${props.category.tournament.slug}.${props.category.slug}`
        : null,
);

let liveChannel: ReturnType<typeof echo.channel> | null = null;

function refreshFromLive(): void {
    router.reload({ only: ['standings', 'days', 'rows', 'challenges'] });
}

onMounted(() => {
    if (liveChannelName.value === null) {
        return;
    }

    liveChannel = echo.channel(liveChannelName.value);
    LIVE_EVENTS.forEach((event) => liveChannel?.listen(event, refreshFromLive));
});

onBeforeUnmount(() => {
    LIVE_EVENTS.forEach((event) =>
        liveChannel?.stopListening(event, refreshFromLive),
    );
    liveChannel = null;
});

const breadcrumb = computed(() => [
    { label: 'Eventos', href: '/torneos' },
    ...(props.category.tournament
        ? [
              {
                  label: props.category.tournament.name,
                  href: `/torneos/${props.category.tournament.slug}`,
              },
          ]
        : []),
    { label: props.category.name },
]);

const metrics = computed(() => [
    {
        value: String(props.category.registrations_count ?? 0),
        label: 'Inscritos',
    },
    { value: String(props.category.number_of_days), label: 'Días' },
    { value: String(props.category.rounds_per_day), label: 'Lazos por día' },
    { value: String(props.category.total_rounds), label: 'Lazos totales' },
]);

/** La fila del líder se enciende; las demás alternan cuero y noche. */
function rowTone(position: number | null, index: number): string {
    if (position === 1) {
        return 'bg-rienda-fuego/10';
    }

    return index % 2 ? 'bg-rienda-surco' : 'bg-rienda-noche';
}

function riderUrl(publicId: string): string {
    return `/jinetes/${publicId}`;
}
</script>

<template>
    <Head :title="`${category.name} · ${category.tournament?.name ?? ''}`" />

    <LandingShell :cta="{ label: 'Inicio', href: '/', live: false }">
        <!-- Ficha de la categoría -->
        <section
            class="bg-[radial-gradient(120%_90%_at_8%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-noche)_62%)] px-5 py-12 sm:px-8 lg:px-10"
        >
            <LandingBreadcrumb :items="breadcrumb" class="mb-7" />

            <div class="flex flex-wrap items-center gap-3">
                <h1
                    class="font-display text-[clamp(2.5rem,5.6vw,4.5rem)] leading-[0.95] text-rienda-crema uppercase"
                >
                    {{ category.name }}
                </h1>
                <span
                    class="rounded-full border px-3 py-1 font-condensed text-sm tracking-[0.14em] uppercase"
                    :class="categoryTone(category.status)"
                >
                    {{ category.status_label }}
                </span>
            </div>

            <p
                v-if="category.tournament"
                class="mt-2.5 font-condensed text-base tracking-[0.12em] text-rienda-arena uppercase"
            >
                {{ category.tournament.name }}
            </p>

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

        <!-- Clasificación acumulada -->
        <section class="px-5 py-14 sm:px-8 lg:px-10">
            <LandingHeading
                kicker="Tabla de posiciones"
                title="Clasificación general"
            />

            <p
                v-if="standings.data.length === 0"
                class="mt-7 rounded-[20px] border border-dashed border-rienda-crema/15 p-12 text-center text-sm text-rienda-arena"
            >
                Todavía sin tiempos: la clasificación se arma con los lazos que
                el juez va registrando.
            </p>

            <div
                v-else
                class="mt-7 overflow-hidden rounded-[20px] border border-rienda-crema/10"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[640px] border-collapse text-left"
                    >
                        <thead
                            class="bg-rienda-tierra font-condensed text-sm tracking-[0.16em] text-rienda-arena uppercase"
                        >
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Pos</th>
                                <th class="px-2 py-3.5 font-medium">Dorsal</th>
                                <th class="px-2 py-3.5 font-medium">Vaquero</th>
                                <th class="px-2 py-3.5 font-medium">Caballo</th>
                                <th class="px-2 py-3.5 text-right font-medium">
                                    Aciertos
                                </th>
                                <th class="px-2 py-3.5 text-right font-medium">
                                    Lazos
                                </th>
                                <th class="px-5 py-3.5 text-right font-medium">
                                    Tiempo
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(standing, index) in standings.data"
                                :key="standing.registration.registration_number"
                                class="border-t border-rienda-crema/6 transition-colors hover:bg-rienda-cuero"
                                :class="rowTone(standing.position, index)"
                            >
                                <td class="px-5 py-3.5">
                                    <span
                                        class="font-display text-[20px] tabular-nums"
                                        :class="
                                            standing.position === 1
                                                ? 'text-rienda-dorado'
                                                : 'text-rienda-sombra'
                                        "
                                    >
                                        {{ standing.position ?? '—' }}
                                    </span>
                                </td>
                                <td
                                    class="px-2 py-3.5 font-condensed text-[17px] text-rienda-sombra tabular-nums"
                                >
                                    {{
                                        standing.registration
                                            .registration_number
                                    }}
                                </td>
                                <td class="px-2 py-3.5">
                                    <Link
                                        :href="
                                            riderUrl(
                                                standing.registration
                                                    .participant.public_id,
                                            )
                                        "
                                        class="font-condensed text-[20px] font-semibold tracking-[0.04em] text-rienda-crema uppercase transition-colors hover:text-rienda-dorado"
                                    >
                                        {{
                                            standing.registration.participant
                                                .full_name
                                        }}
                                    </Link>
                                </td>
                                <td
                                    class="px-2 py-3.5 text-[14.5px] text-rienda-arena"
                                >
                                    {{ standing.registration.horse ?? '—' }}
                                </td>
                                <td
                                    class="px-2 py-3.5 text-right font-condensed text-[17px] text-rienda-crema tabular-nums"
                                >
                                    {{ standing.total_successful_rounds }}
                                </td>
                                <td
                                    class="px-2 py-3.5 text-right font-condensed text-[17px] text-rienda-sombra tabular-nums"
                                >
                                    {{ standing.recorded_rounds }}
                                </td>
                                <td
                                    class="px-5 py-3.5 text-right font-display text-[20px] text-rienda-dorado tabular-nums"
                                >
                                    {{ standing.total_time_formatted }}
                                    <span
                                        v-if="standing.total_penalty_ms > 0"
                                        class="block font-condensed text-[13px] tracking-[0.12em] text-rienda-ascua uppercase"
                                    >
                                        +{{ standing.total_penalty_formatted }}
                                        penal.
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Retos -->
        <section
            v-if="challenges.data.length > 0"
            class="px-5 pb-14 sm:px-8 lg:px-10"
        >
            <div class="flex flex-wrap items-end justify-between gap-5">
                <LandingHeading kicker="Cara a cara" title="Retos" />
                <p
                    class="max-w-[36ch] text-base leading-relaxed text-rienda-arena"
                >
                    Dos vaqueros de esta categoría, cara a cara. Gana quien
                    acierte más lazos; el menor tiempo acumulado desempata.
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

        <!-- Clasificación día a día -->
        <section class="bg-rienda-carbon px-5 py-14 sm:px-8 lg:px-10">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <LandingHeading kicker="Manga a manga" title="Día a día" />
                <p
                    class="max-w-[36ch] text-base leading-relaxed text-rienda-arena"
                >
                    El acumulado de cada vaquero en cada jornada, con los
                    aciertos que logró ese día.
                </p>
            </div>

            <p
                v-if="rows.length === 0"
                class="mt-7 rounded-[20px] border border-dashed border-rienda-crema/15 p-12 text-center text-sm text-rienda-arena"
            >
                Sin inscritos todavía. Cuando se inscriban vaqueros, aquí
                aparece su detalle por jornada.
            </p>

            <div
                v-else
                class="mt-7 overflow-hidden rounded-[20px] border border-rienda-crema/10"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[640px] border-collapse text-left"
                    >
                        <thead
                            class="bg-rienda-tierra font-condensed text-sm tracking-[0.16em] text-rienda-arena uppercase"
                        >
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Dorsal</th>
                                <th class="px-2 py-3.5 font-medium">Vaquero</th>
                                <th class="px-2 py-3.5 font-medium">Caballo</th>
                                <th
                                    v-for="day in days"
                                    :key="day.day_number"
                                    class="px-3 py-3.5 text-center font-medium"
                                >
                                    <span class="block"
                                        >Día {{ day.day_number }}</span
                                    >
                                    <span
                                        class="block text-[13px] text-rienda-sombra"
                                    >
                                        {{ formatDate(day.date) }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in rows"
                                :key="row.registration_number"
                                class="border-t border-rienda-crema/6 transition-colors hover:bg-rienda-cuero"
                                :class="
                                    index % 2
                                        ? 'bg-rienda-surco'
                                        : 'bg-rienda-noche'
                                "
                            >
                                <td
                                    class="px-5 py-3.5 font-condensed text-[17px] text-rienda-sombra tabular-nums"
                                >
                                    {{ row.registration_number }}
                                </td>
                                <td class="px-2 py-3.5">
                                    <Link
                                        :href="
                                            riderUrl(row.participant.public_id)
                                        "
                                        class="font-condensed text-[20px] font-semibold tracking-[0.04em] text-rienda-crema uppercase transition-colors hover:text-rienda-dorado"
                                    >
                                        {{ row.participant.full_name }}
                                    </Link>
                                </td>
                                <td
                                    class="px-2 py-3.5 text-[14.5px] text-rienda-arena"
                                >
                                    {{ row.horse ?? '—' }}
                                </td>
                                <td
                                    v-for="(cell, cellIndex) in row.per_day"
                                    :key="cellIndex"
                                    class="px-3 py-3.5 text-center"
                                >
                                    <span
                                        v-if="cell === null"
                                        class="text-rienda-sombra"
                                    >
                                        —
                                    </span>
                                    <template v-else>
                                        <p
                                            class="font-display text-[19px] tabular-nums"
                                            :class="
                                                cell.successful_rounds > 0
                                                    ? 'text-rienda-dorado'
                                                    : 'text-rienda-sombra'
                                            "
                                        >
                                            {{ cell.time_formatted }}
                                        </p>
                                        <p
                                            class="font-condensed text-[13px] tracking-[0.12em] text-rienda-sombra uppercase"
                                        >
                                            {{
                                                plural(
                                                    cell.successful_rounds,
                                                    'acierto',
                                                    'aciertos',
                                                )
                                            }}
                                        </p>
                                        <!--
                                            El tiempo del día ya trae el castigo
                                            dentro: se dice cuánto para que la
                                            cifra se pueda explicar.
                                        -->
                                        <p
                                            v-if="cell.penalty_ms > 0"
                                            class="font-condensed text-[13px] tracking-[0.12em] text-rienda-ascua uppercase"
                                        >
                                            +{{ cell.penalty_formatted }} penal.
                                        </p>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </LandingShell>
</template>
