<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, MapPin, Trophy } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import echo from '@/echo';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';
import LandingHeading from '@/modules/public/components/LandingHeading.vue';
import LandingShell from '@/modules/public/components/LandingShell.vue';
import { tournamentTone } from '@/modules/public/tones';
import type {
    LandingActiveBlock,
    LandingBoard,
    LandingResult,
    LandingRider,
} from '@/modules/public/types';
import {
    dayCount,
    formatDate,
    formatDateRange,
    plural,
    roundLabel,
} from '@/modules/shared/format';
import type { PublicTournament } from '@/modules/tournaments/types';

/**
 * La landing pública de RIENDA.
 *
 * No es una página corporativa: es el cartel del torneo que está en pista.
 * Arriba, el bloque en vivo con su marcador; debajo, la clasificación de la
 * categoría en competencia, los jinetes con más victorias, el calendario de la
 * temporada y los últimos tiempos cronometrados.
 *
 * Como el resto de la cara pública, trae su propia cabecera y su propio pie
 * con `LandingShell`: el tema de cuero y brasa es el de toda la cara pública,
 * no el del panel.
 */
const props = defineProps<{
    active: LandingActiveBlock | null;
    upcoming: { data: PublicTournament[] };
    latest_results: LandingResult[];
    featured: LandingRider[];
}>();

// ------------------------------------------------------------- presentación

/** Las tarjetas del podio, de oro a cuero, como en el cartel del diseño. */
const PODIUM_STYLES = [
    {
        card: 'bg-rienda-fuego border-rienda-dorado min-h-[228px]',
        ink: 'text-rienda-noche',
        soft: 'text-rienda-noche/70',
    },
    {
        card: 'bg-rienda-cuero border-rienda-crema/15 min-h-[206px]',
        ink: 'text-rienda-crema',
        soft: 'text-rienda-arena',
    },
    {
        card: 'bg-rienda-tierra border-rienda-crema/12 min-h-[190px]',
        ink: 'text-rienda-crema',
        soft: 'text-rienda-arena',
    },
] as const;

function categoryUrl(tournamentSlug: string, categorySlug: string): string {
    return `/torneos/${tournamentSlug}/categorias/${categorySlug}`;
}

/** "2026-08-15" → "15" */
function dayNumber(date: string | null): string {
    return date === null ? '—' : date.slice(8, 10);
}

/** "2026-08-15" → "ago" */
function monthName(date: string | null): string {
    if (date === null) {
        return '';
    }

    return new Date(`${date}T00:00:00`)
        .toLocaleDateString('es-CO', { month: 'short' })
        .replace('.', '');
}

// ------------------------------------------------------------------ cabecera

const nextTournament = computed<PublicTournament | null>(
    () => props.upcoming.data[0] ?? null,
);

/**
 * La cinta superior. Se rellena con lo que de verdad está pasando y, si hay
 * poco que contar, se repite hasta cubrir el ancho de la pantalla.
 */
const ticker = computed(() => {
    const messages: string[] = [];

    if (props.active !== null) {
        const { tournament } = props.active;

        messages.push(
            `${tournament.name} · en vivo desde ${tournament.location ?? 'Colombia'}`,
        );

        props.active.boards.forEach((entry) => {
            messages.push(
                entry.round === null
                    ? `${entry.category.name} en pista`
                    : `${roundLabel(entry.round)} · ${entry.category.name} en pista`,
            );
        });
    }

    if (nextTournament.value !== null) {
        messages.push(
            `Próximo: ${nextTournament.value.name} · ${formatDate(nextTournament.value.start_date)}`,
        );
    }

    if (props.latest_results.length > 0) {
        const [result] = props.latest_results;

        messages.push(
            `Último tiempo: ${result.participant.full_name} · ${result.time_formatted}s`,
        );
    }

    messages.push('Rienda.com · resultados del lazo, en vivo');

    while (messages.length < 6) {
        messages.push(...messages.slice(0, Math.min(messages.length, 6)));
    }

    return messages;
});

const cta = computed(() =>
    props.active === null
        ? { label: 'Ver eventos', href: '/torneos', live: false }
        : { label: 'Ver en vivo', href: '#envivo', live: true },
);

// ----------------------------------------------------------- bloque en vivo

const boards = computed<LandingBoard[]>(() => props.active?.boards ?? []);

/**
 * La categoría que se está mirando. Arranca en la primera en pista y solo
 * cambia cuando el visitante elige otra: los refrescos en vivo reemplazan los
 * datos, no la selección.
 */
const selectedCategory = ref<string | null>(null);

const board = computed<LandingBoard | null>(
    () =>
        boards.value.find(
            (entry) => entry.category.slug === selectedCategory.value,
        ) ??
        boards.value[0] ??
        null,
);

function selectBoard(slug: string): void {
    selectedCategory.value = slug;
}

/** Si una categoría tiene marcador propio, el cartel la deja elegir. */
function isLive(slug: string): boolean {
    return boards.value.some((entry) => entry.category.slug === slug);
}

const standings = computed(() => board.value?.standings.data ?? []);

/** El líder de la categoría en pista, si ya hay algún tiempo registrado. */
const leader = computed(() => standings.value[0] ?? null);

const podium = computed(() => standings.value.slice(0, 3));

const chasers = computed(() => standings.value.slice(3));

const lastResult = computed<LandingResult | null>(
    () => props.latest_results[0] ?? null,
);

const liveUrl = computed(() =>
    props.active === null || board.value === null
        ? '/torneos'
        : categoryUrl(props.active.tournament.slug, board.value.category.slug),
);

/** Los anclajes de la cabecera: solo las secciones que la página va a pintar. */
const sections = computed(() => {
    const anchors = [];

    if (props.active !== null) {
        anchors.push({ label: 'En vivo', href: '#envivo' });
    }

    if (podium.value.length > 0) {
        anchors.push({ label: 'Clasificación', href: '#clasificacion' });
    }

    anchors.push(
        { label: 'Destacados', href: '#vaqueros' },
        { label: 'Calendario', href: '#calendario' },
        { label: 'Resultados', href: '#resultados' },
    );

    return anchors;
});

const metrics = computed(() => {
    if (props.active === null) {
        return [];
    }

    const { tournament, categories } = props.active;

    const registrations = categories.reduce(
        (total, category) => total + (category.registrations_count ?? 0),
        0,
    );

    return [
        { value: String(categories.length), label: 'Categorías' },
        { value: String(registrations), label: 'Vaqueros inscritos' },
        {
            value: dayCount(tournament.start_date, tournament.end_date),
            label: 'Días de feria',
        },
        {
            value: String(boards.value.length),
            label: 'Categorías en pista',
        },
    ];
});

// -------------------------------------------------------------- tiempo real

/**
 * Tiempo real: mientras hay un torneo «en vivo», la landing escucha los
 * eventos de su categoría y refresca el bloque activo (clasificación, día y
 * ronda) cuando cambian los resultados.
 *
 * El `.` inicial evita que Echo anteponga el namespace `App\Events\...`: los
 * eventos usan `broadcastAs()` y en el wire llegan con el nombre desnudo.
 */
const LIVE_EVENTS = [
    '.round-result.recorded',
    '.round-result.updated',
    '.round.started',
    '.round.completed',
    '.competition-day.started',
    '.competition-day.completed',
] as const;

const liveChannelNames = computed(() => {
    if (props.active === null) {
        return [];
    }

    const { slug } = props.active.tournament;

    return boards.value.map(
        (entry) => `public-category.${slug}.${entry.category.slug}`,
    );
});

let liveChannels: ReturnType<typeof echo.channel>[] = [];

/**
 * Los eventos de resultado traen al `participant` que acaba de puntuar: además
 * del bloque activo se refresca la lista de últimos resultados. Los eventos de
 * ronda y día solo actualizan el bloque activo.
 */
function refreshActiveFromLive(payload?: { participant?: string }): void {
    const only =
        payload?.participant !== undefined
            ? ['active', 'latest_results']
            : ['active'];
    router.reload({ only });
}

function unsubscribeLive(): void {
    liveChannels.forEach((channel) => {
        LIVE_EVENTS.forEach((event) =>
            channel.stopListening(event, refreshActiveFromLive),
        );
    });

    liveChannels = [];
}

/**
 * Se escucha una categoría por marcador: cualquiera que cronometre refresca la
 * landing, esté o no siendo la que se mira en ese momento.
 */
function subscribeLive(): void {
    liveChannels = liveChannelNames.value.map((name) => {
        const channel = echo.channel(name);

        LIVE_EVENTS.forEach((event) =>
            channel.listen(event, refreshActiveFromLive),
        );

        return channel;
    });
}

watch(liveChannelNames, (names, previous) => {
    if (names.join() === previous.join()) {
        return;
    }

    unsubscribeLive();
    subscribeLive();
});

onMounted(subscribeLive);
onBeforeUnmount(unsubscribeLive);
</script>

<template>
    <Head title="Inicio" />

    <LandingShell :ticker="ticker" :sections="sections" :pages="[]" :cta="cta">
        <!-- En vivo -->
        <section
            id="envivo"
            class="scroll-mt-24 px-5 py-14 sm:px-8 lg:px-10"
            :class="
                active
                    ? 'bg-[radial-gradient(120%_90%_at_8%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-noche)_62%)]'
                    : 'bg-[radial-gradient(120%_90%_at_50%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-noche)_62%)]'
            "
        >
            <template v-if="active">
                <div class="mb-6 flex flex-wrap items-center gap-3">
                    <span
                        class="flex items-center gap-2 rounded-full bg-rienda-brasa px-3.5 py-1.5 font-condensed text-sm font-bold tracking-[0.18em] text-white uppercase"
                    >
                        <span
                            class="size-[7px] animate-pulso rounded-full bg-white motion-reduce:animate-none"
                        />
                        En vivo ahora
                    </span>
                    <span
                        v-if="board"
                        class="font-condensed text-[15px] tracking-[0.16em] text-rienda-arena uppercase"
                    >
                        {{
                            plural(
                                board.category.registrations_count ?? 0,
                                'inscrito',
                                'inscritos',
                            )
                        }}
                        ·
                        {{
                            board.day
                                ? `Día ${board.day.day_number}`
                                : 'Día por abrir'
                        }}
                        ·
                        {{
                            board.round
                                ? roundLabel(board.round)
                                : 'Lazo por abrir'
                        }}
                    </span>
                </div>

                <div
                    class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.85fr)]"
                >
                    <div class="flex min-w-0 flex-col gap-5">
                        <h1
                            class="font-display text-[clamp(3rem,6.4vw,6rem)] leading-[0.92] text-balance text-rienda-crema uppercase"
                        >
                            {{ active.tournament.name }}
                        </h1>

                        <!--
                            Las categorías en pista son botones: cambian el
                            marcador sin recargar. Las demás siguen siendo un
                            enlace a su clasificación.
                        -->
                        <div class="flex flex-wrap gap-2.5">
                            <template
                                v-for="category in active.categories"
                                :key="category.slug"
                            >
                                <button
                                    v-if="isLive(category.slug)"
                                    type="button"
                                    class="flex items-center gap-2 rounded-full border px-3.5 py-1.5 font-condensed text-sm tracking-[0.14em] uppercase transition-colors"
                                    :class="
                                        board?.category.slug === category.slug
                                            ? 'border-rienda-fuego bg-rienda-fuego text-rienda-noche'
                                            : 'border-rienda-crema/20 text-rienda-crema/85 hover:border-rienda-fuego hover:text-rienda-crema'
                                    "
                                    @click="selectBoard(category.slug)"
                                >
                                    <span
                                        class="size-[6px] animate-pulso rounded-full motion-reduce:animate-none"
                                        :class="
                                            board?.category.slug ===
                                            category.slug
                                                ? 'bg-rienda-noche'
                                                : 'bg-rienda-brasa'
                                        "
                                    />
                                    {{ category.name }}
                                </button>
                                <Link
                                    v-else
                                    :href="
                                        categoryUrl(
                                            active.tournament.slug,
                                            category.slug,
                                        )
                                    "
                                    class="rounded-full border border-rienda-crema/20 px-3.5 py-1.5 font-condensed text-sm tracking-[0.14em] text-rienda-crema/85 uppercase transition-colors hover:border-rienda-fuego hover:text-rienda-crema"
                                >
                                    {{ category.name }}
                                </Link>
                            </template>
                        </div>

                        <div
                            class="flex flex-wrap items-end justify-between gap-6 rounded-[20px] border border-rienda-crema/10 bg-rienda-tierra px-6 py-5"
                        >
                            <div class="min-w-0">
                                <p
                                    class="font-condensed text-sm tracking-[0.18em] text-rienda-dorado uppercase"
                                >
                                    {{
                                        lastResult
                                            ? 'Último en la manga'
                                            : 'En la manga'
                                    }}
                                </p>
                                <p
                                    class="truncate font-display text-[clamp(1.5rem,3vw,2.25rem)] tracking-[0.02em] text-rienda-crema uppercase"
                                >
                                    {{
                                        lastResult?.participant.full_name ??
                                        'Sin tiempos todavía'
                                    }}
                                </p>
                                <p
                                    class="truncate font-condensed text-base tracking-[0.1em] text-rienda-arena uppercase"
                                >
                                    {{
                                        lastResult?.horse ??
                                        active.tournament.location ??
                                        'Colombia'
                                    }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p
                                    class="font-condensed text-sm tracking-[0.18em] text-rienda-arena uppercase"
                                >
                                    Mejor acumulado
                                </p>
                                <p
                                    class="font-display text-[clamp(1.75rem,4vw,2.75rem)] leading-none text-rienda-dorado tabular-nums"
                                >
                                    {{ leader?.total_time_formatted ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                            <div
                                v-for="metric in metrics"
                                :key="metric.label"
                                class="rounded-2xl border border-rienda-crema/8 bg-rienda-tierra px-4.5 py-4"
                            >
                                <p
                                    class="font-display text-[28px] text-rienda-crema"
                                >
                                    {{ metric.value }}
                                </p>
                                <p
                                    class="font-condensed text-sm tracking-[0.14em] text-rienda-arena uppercase"
                                >
                                    {{ metric.label }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <aside
                        class="flex flex-col gap-4 rounded-[22px] border border-rienda-crema/10 bg-rienda-tierra p-6 shadow-[0_20px_60px_rgba(0,0,0,0.35)]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="font-condensed text-[15px] tracking-[0.18em] text-rienda-dorado uppercase"
                            >
                                Marcador en vivo
                            </span>
                            <span
                                class="truncate font-condensed text-sm tracking-[0.1em] text-rienda-arena uppercase"
                            >
                                {{ board?.category.name }}
                            </span>
                        </div>

                        <p
                            v-if="standings.length === 0"
                            class="py-6 text-sm text-rienda-arena"
                        >
                            Aún no hay tiempos registrados en esta categoría.
                        </p>

                        <template v-else>
                            <div
                                v-for="row in standings.slice(0, 5)"
                                :key="row.registration.registration_number"
                                class="flex items-center gap-3.5 border-b border-rienda-crema/7 py-2.5"
                            >
                                <span
                                    class="w-7 font-display text-[19px] text-rienda-sombra"
                                >
                                    {{
                                        String(row.position ?? 0).padStart(
                                            2,
                                            '0',
                                        )
                                    }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="`/jinetes/${row.registration.participant.public_id}`"
                                        class="block truncate font-condensed text-[19px] font-semibold tracking-[0.04em] text-rienda-crema uppercase hover:text-rienda-dorado"
                                    >
                                        {{
                                            row.registration.participant
                                                .full_name
                                        }}
                                    </Link>
                                    <p
                                        class="truncate text-[12.5px] text-rienda-sombra"
                                    >
                                        {{
                                            row.registration.horse ??
                                            'Sin caballo'
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="font-display text-[21px] text-rienda-dorado tabular-nums"
                                >
                                    {{ row.total_time_formatted }}
                                </span>
                            </div>
                        </template>

                        <Link
                            :href="liveUrl"
                            class="mt-1 flex items-center justify-center gap-2 rounded-full bg-rienda-fuego px-4 py-2.5 font-condensed text-[15px] font-bold tracking-[0.14em] text-rienda-noche uppercase transition-colors hover:bg-rienda-dorado"
                        >
                            Ver la clasificación completa
                            <ArrowRight class="size-4" />
                        </Link>
                    </aside>
                </div>
            </template>

            <!-- Sin evento en pista: la landing sigue anunciando la temporada. -->
            <template v-else>
                <div
                    class="mx-auto flex max-w-3xl flex-col items-center gap-5 py-8 text-center"
                >
                    <span
                        class="font-condensed text-[15px] tracking-[0.22em] text-rienda-fuego uppercase"
                    >
                        Eventos de vaquería y lazo · Colombia
                    </span>
                    <h1
                        class="font-display text-[clamp(3rem,6.4vw,6rem)] leading-[0.92] text-balance text-rienda-crema uppercase"
                    >
                        Los tiempos<br />del lazo, en vivo
                    </h1>
                    <p
                        class="max-w-[46ch] text-base leading-relaxed text-rienda-arena"
                    >
                        Ahora mismo no hay ninguna manga en pista. Cuando un
                        evento arranca, esta página se convierte en su marcador:
                        clasificación por categoría, tiempo a tiempo.
                    </p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <Link
                            href="/torneos"
                            class="flex items-center gap-2 rounded-full bg-rienda-fuego px-5 py-2.5 font-condensed text-[15px] font-bold tracking-[0.14em] text-rienda-noche uppercase transition-colors hover:bg-rienda-dorado"
                        >
                            Ver eventos
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            href="/jinetes"
                            class="rounded-full border border-rienda-crema/20 px-5 py-2.5 font-condensed text-[15px] font-bold tracking-[0.14em] text-rienda-crema uppercase transition-colors hover:border-rienda-fuego"
                        >
                            Ver vaqueros
                        </Link>
                    </div>

                    <div
                        v-if="nextTournament"
                        class="mt-3 w-full max-w-md rounded-[22px] border border-rienda-crema/10 bg-rienda-tierra p-6"
                    >
                        <p
                            class="font-condensed text-sm tracking-[0.18em] text-rienda-dorado uppercase"
                        >
                            Próximo evento
                        </p>
                        <p
                            class="mt-2 font-display text-[clamp(1.75rem,3vw,2.25rem)] leading-tight text-rienda-crema uppercase"
                        >
                            {{ nextTournament.name }}
                        </p>
                        <p
                            class="mt-1 font-condensed text-base tracking-[0.1em] text-rienda-arena uppercase"
                        >
                            {{
                                nextTournament.location ?? 'Sede por confirmar'
                            }}
                        </p>
                        <p class="mt-3 text-sm text-rienda-sombra">
                            {{
                                formatDateRange(
                                    nextTournament.start_date,
                                    nextTournament.end_date,
                                )
                            }}
                        </p>
                        <Link
                            :href="`/torneos/${nextTournament.slug}`"
                            class="mt-5 flex items-center justify-center gap-2 rounded-full bg-rienda-fuego px-4 py-2.5 font-condensed text-[15px] font-bold tracking-[0.14em] text-rienda-noche uppercase transition-colors hover:bg-rienda-dorado"
                        >
                            Ver el evento
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </template>
        </section>

        <!-- Clasificación de la categoría en pista -->
        <section
            v-if="active && board && podium.length > 0"
            id="clasificacion"
            class="scroll-mt-24 border-t border-rienda-crema/8 bg-rienda-noche px-5 py-16 sm:px-8 lg:px-10"
        >
            <div class="mb-7 flex flex-wrap items-end justify-between gap-5">
                <LandingHeading
                    kicker="Tabla de posiciones"
                    :title="`Ranking · ${board.category.name}`"
                />
                <Link
                    :href="`/torneos/${active.tournament.slug}`"
                    class="rounded-full border border-rienda-crema/20 px-4 py-2 font-condensed text-[15px] font-semibold tracking-[0.14em] text-rienda-crema/85 uppercase transition-colors hover:border-rienda-fuego hover:text-rienda-crema"
                >
                    Todas las categorías
                </Link>
            </div>

            <div class="mb-7 grid items-end gap-4.5 sm:grid-cols-3">
                <div
                    v-for="(row, index) in podium"
                    :key="row.registration.registration_number"
                    class="flex flex-col gap-3.5 rounded-[22px] border p-6"
                    :class="PODIUM_STYLES[index].card"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span
                            class="font-display text-[56px] leading-[0.8]"
                            :class="PODIUM_STYLES[index].ink"
                        >
                            {{ row.position ?? index + 1 }}
                        </span>
                        <span
                            class="rounded-full bg-rienda-noche/35 px-3 py-1.5 font-condensed text-[13px] tracking-[0.16em] uppercase"
                            :class="PODIUM_STYLES[index].ink"
                        >
                            {{ row.total_successful_rounds }} aciertos
                        </span>
                    </div>
                    <div class="min-w-0">
                        <Link
                            :href="`/jinetes/${row.registration.participant.public_id}`"
                            class="block font-display text-[26px] leading-tight uppercase hover:underline"
                            :class="PODIUM_STYLES[index].ink"
                        >
                            {{ row.registration.participant.full_name }}
                        </Link>
                        <p
                            class="truncate font-condensed text-base tracking-[0.1em] uppercase"
                            :class="PODIUM_STYLES[index].soft"
                        >
                            {{ row.registration.horse ?? 'Sin caballo' }}
                        </p>
                    </div>
                    <div class="mt-auto flex items-baseline gap-2">
                        <span
                            class="font-display text-[38px] leading-none tabular-nums"
                            :class="PODIUM_STYLES[index].ink"
                        >
                            {{ row.total_time_formatted }}
                        </span>
                        <span
                            class="font-condensed text-sm tracking-[0.16em] uppercase"
                            :class="PODIUM_STYLES[index].soft"
                        >
                            acumulado
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="chasers.length > 0"
                class="overflow-hidden rounded-[20px] border border-rienda-crema/10"
            >
                <div
                    class="grid grid-cols-[52px_minmax(0,2.1fr)_0.9fr_0.9fr] gap-3 bg-rienda-tierra px-5 py-3.5 font-condensed text-sm tracking-[0.16em] text-rienda-arena uppercase sm:grid-cols-[64px_minmax(0,2.1fr)_minmax(0,1.6fr)_0.9fr_0.9fr]"
                >
                    <span>Pos</span>
                    <span>Vaquero</span>
                    <span class="hidden sm:block">Caballo</span>
                    <span class="text-right">Aciertos</span>
                    <span class="text-right">Tiempo</span>
                </div>
                <div
                    v-for="(row, index) in chasers"
                    :key="row.registration.registration_number"
                    class="grid grid-cols-[52px_minmax(0,2.1fr)_0.9fr_0.9fr] items-center gap-3 border-t border-rienda-crema/6 px-5 py-3.5 transition-colors hover:bg-rienda-cuero sm:grid-cols-[64px_minmax(0,2.1fr)_minmax(0,1.6fr)_0.9fr_0.9fr]"
                    :class="index % 2 ? 'bg-rienda-surco' : 'bg-rienda-noche'"
                >
                    <span class="font-display text-[20px] text-rienda-sombra">
                        {{ String(row.position ?? index + 4).padStart(2, '0') }}
                    </span>
                    <Link
                        :href="`/jinetes/${row.registration.participant.public_id}`"
                        class="truncate font-condensed text-[20px] font-semibold tracking-[0.04em] text-rienda-crema uppercase hover:text-rienda-dorado"
                    >
                        {{ row.registration.participant.full_name }}
                    </Link>
                    <span
                        class="hidden truncate text-[14.5px] text-rienda-arena sm:block"
                    >
                        {{ row.registration.horse ?? '—' }}
                    </span>
                    <span
                        class="text-right font-condensed text-[17px] text-rienda-crema/85 tabular-nums"
                    >
                        {{ row.total_successful_rounds }}
                    </span>
                    <span
                        class="text-right font-display text-[20px] text-rienda-dorado tabular-nums"
                    >
                        {{ row.total_time_formatted }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Vaqueros destacados -->
        <section
            id="vaqueros"
            class="scroll-mt-24 bg-rienda-carbon px-5 py-16 sm:px-8 lg:px-10"
        >
            <LandingHeading
                kicker="Historial de RIENDA"
                title="Vaqueros destacados"
            />

            <p
                v-if="featured.length === 0"
                class="mt-8 rounded-[20px] border border-dashed border-rienda-crema/15 p-10 text-center text-sm text-rienda-arena"
            >
                Todavía no hay vaqueros con historial.
            </p>

            <div
                v-else
                class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <Link
                    v-for="rider in featured"
                    :key="rider.public_id"
                    :href="`/jinetes/${rider.public_id}`"
                    class="flex flex-col gap-3 rounded-[20px] border border-rienda-crema/8 bg-rienda-tierra p-6 transition-colors hover:border-rienda-fuego/55"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span
                            class="truncate font-condensed text-sm tracking-[0.18em] text-rienda-dorado uppercase"
                        >
                            {{ rider.city ?? 'Colombia' }}
                        </span>
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-rienda-fuego/18 text-rienda-dorado"
                        >
                            <Trophy class="size-4" />
                        </span>
                    </div>
                    <div class="flex min-w-0 items-center gap-3">
                        <ParticipantAvatar
                            :name="rider.full_name"
                            :photo-url="rider.photo_url"
                            tone="cartel"
                            class="size-11 text-[17px] font-bold"
                        />
                        <div class="min-w-0">
                            <p
                                class="truncate font-display text-[22px] leading-tight text-rienda-crema uppercase"
                            >
                                {{ rider.full_name }}
                            </p>
                            <p
                                class="truncate font-condensed text-base tracking-[0.1em] text-rienda-arena uppercase"
                            >
                                {{
                                    plural(
                                        rider.horses_count ?? 0,
                                        'caballo',
                                        'caballos',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex justify-between border-t border-rienda-crema/8 pt-2.5 font-condensed text-[15px] tracking-[0.08em] text-rienda-sombra uppercase"
                    >
                        <span>
                            {{
                                plural(
                                    rider.registrations_count ?? 0,
                                    'evento',
                                    'eventos',
                                )
                            }}
                        </span>
                        <span class="text-rienda-fuego">
                            {{
                                plural(
                                    rider.wins_count,
                                    'victoria',
                                    'victorias',
                                )
                            }}
                        </span>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Calendario -->
        <section
            id="calendario"
            class="scroll-mt-24 bg-rienda-noche px-5 py-16 sm:px-8 lg:px-10"
        >
            <div
                class="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)]"
            >
                <div>
                    <LandingHeading
                        kicker="Temporada"
                        title="Próximos eventos"
                    />
                    <p
                        class="mt-3.5 max-w-[34ch] text-base leading-relaxed text-rienda-arena"
                    >
                        Ferias ganaderas y clubes de vaquería de todo el país.
                        Cada evento abre su clasificación en cuanto suena la
                        primera manga.
                    </p>
                    <Link
                        href="/torneos"
                        class="mt-5 inline-flex items-center gap-2 rounded-full border border-rienda-crema/20 px-4 py-2 font-condensed text-[15px] font-semibold tracking-[0.14em] text-rienda-crema uppercase transition-colors hover:border-rienda-fuego"
                    >
                        Ver el calendario completo
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <p
                    v-if="upcoming.data.length === 0"
                    class="rounded-[20px] border border-dashed border-rienda-crema/15 p-10 text-center text-sm text-rienda-arena"
                >
                    Todavía no hay eventos publicados.
                </p>

                <div v-else class="flex flex-col gap-3">
                    <Link
                        v-for="tournament in upcoming.data"
                        :key="tournament.slug"
                        :href="`/torneos/${tournament.slug}`"
                        class="grid grid-cols-[64px_minmax(0,1fr)] items-center gap-5 rounded-[18px] border border-rienda-crema/8 bg-rienda-tierra px-5 py-5 transition-colors hover:bg-rienda-cuero sm:grid-cols-[88px_minmax(0,1fr)_auto]"
                    >
                        <div class="text-center">
                            <p
                                class="font-display text-[30px] leading-none text-rienda-dorado"
                            >
                                {{ dayNumber(tournament.start_date) }}
                            </p>
                            <p
                                class="font-condensed text-sm tracking-[0.18em] text-rienda-sombra uppercase"
                            >
                                {{ monthName(tournament.start_date) }}
                            </p>
                        </div>
                        <div class="min-w-0">
                            <p
                                class="truncate font-display text-[21px] text-rienda-crema uppercase"
                            >
                                {{ tournament.name }}
                            </p>
                            <p
                                class="flex items-center gap-1.5 truncate font-condensed text-base tracking-[0.08em] text-rienda-arena uppercase"
                            >
                                <MapPin class="size-3.5 shrink-0" />
                                {{
                                    tournament.location ?? 'Sede por confirmar'
                                }}
                            </p>
                        </div>
                        <span
                            class="col-span-2 justify-self-start rounded-full border px-3.5 py-1.5 font-condensed text-sm tracking-[0.14em] whitespace-nowrap uppercase sm:col-span-1 sm:justify-self-auto"
                            :class="tournamentTone(tournament)"
                        >
                            {{ tournament.status_label }}
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Últimos resultados -->
        <section
            id="resultados"
            class="scroll-mt-24 bg-linear-to-b from-rienda-carbon to-rienda-cuero px-5 py-16 sm:px-8 lg:px-10"
        >
            <div class="mb-7 flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p
                        class="font-condensed text-[15px] tracking-[0.22em] text-rienda-fuego uppercase"
                    >
                        Cronómetro
                    </p>
                    <h2
                        class="mt-3.5 font-display text-[clamp(2.75rem,7vw,6.875rem)] leading-[0.96] text-rienda-crema uppercase"
                    >
                        Últimos<br />tiempos
                    </h2>
                </div>
                <p
                    class="max-w-[36ch] text-base leading-relaxed text-rienda-arena"
                >
                    Cada manga cronometrada entra aquí en cuanto el juez la
                    registra, con su evento, su categoría y su lazo.
                </p>
            </div>

            <p
                v-if="latest_results.length === 0"
                class="rounded-[20px] border border-dashed border-rienda-crema/15 p-10 text-center text-sm text-rienda-arena"
            >
                Todavía no hay resultados registrados.
            </p>

            <div v-else class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="result in latest_results"
                    :key="result.id"
                    class="rounded-[20px] border border-rienda-crema/10 bg-rienda-crema/5 p-5"
                >
                    <p
                        class="truncate font-condensed text-sm tracking-[0.18em] text-rienda-arena uppercase"
                    >
                        {{ result.category.name }} · Lazo
                        {{ result.round_number }}
                    </p>
                    <p
                        class="font-display text-[34px] leading-tight tabular-nums"
                        :class="
                            result.successful
                                ? 'text-rienda-dorado'
                                : 'text-rienda-sombra line-through'
                        "
                    >
                        {{
                            result.successful
                                ? `${result.time_formatted}s`
                                : 'Fallida'
                        }}
                    </p>
                    <Link
                        :href="`/jinetes/${result.participant.public_id}`"
                        class="mt-1 block truncate font-condensed text-[17px] tracking-[0.06em] text-rienda-crema uppercase hover:text-rienda-dorado"
                    >
                        {{ result.participant.full_name }}
                    </Link>
                    <p class="mt-1 truncate text-[13.5px] text-rienda-sombra">
                        {{ result.horse ?? 'Sin caballo' }} ·
                        {{ result.tournament.name }}
                    </p>
                </div>
            </div>
        </section>
    </LandingShell>
</template>
