<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, Check, Flag, Play, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import echo from '@/echo';
import LeaderboardTable from '@/modules/competition/components/LeaderboardTable.vue';
import type {
    EntryResult,
    TournamentDaySummary,
    TournamentQueueEntry,
    TournamentStandings,
} from '@/modules/competition/types';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import { normalizeTimeInput, roundLabel } from '@/modules/shared/format';
import type { TournamentStatus } from '@/modules/tournaments/types';

/**
 * La consola del evento: una sola pantalla para cronometrar el torneo completo.
 *
 * La cola de salida viene de los grupos del torneo y las rondas pendientes de
 * cada categoría. La vista distingue tres momentos de cada vaquero: el que está
 * en salida (panel de captura), el próximo que llaman a pista y el resto de la
 * cola. La clasificación de cada categoría se actualiza sola.
 */
const props = defineProps<{
    tournament: {
        id: number;
        public_id: string;
        name: string;
        slug: string;
        status: TournamentStatus;
        status_label: string;
    };
    days: TournamentDaySummary[];
    selectedDayNumber: number | null;
    queue: TournamentQueueEntry[];
    currentEntry: TournamentQueueEntry | null;
    standings: TournamentStandings[];
}>();

const basePath = `/admin/tournaments/${props.tournament.slug}/competition`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Eventos', href: '/admin/tournaments' }],
    },
});

/** El día de trabajo seleccionado, como resumen. */
const selectedDay = computed(
    () =>
        props.days.find((day) => day.day_number === props.selectedDayNumber) ??
        null,
);

/** El resto de la cola, sin la entrada que ya está en el panel central. */
const upcoming = computed(() =>
    props.queue.filter(
        (entry) => entry.registration_id !== props.currentEntry?.registration_id,
    ),
);

/** El siguiente vaquero que sale a pista cuando el actual registre. */
const nextUp = computed(() => upcoming.value[0] ?? null);

/**
 * El momento de un vaquero dentro de la cola: el que está en salida, el
 * próximo, o el resto.
 */
function roleOf(entry: TournamentQueueEntry): 'salida' | 'proximo' | 'cola' {
    if (entry.registration_id === props.currentEntry?.registration_id) {
        return 'salida';
    }

    if (nextUp.value !== null && entry.registration_id === nextUp.value.registration_id) {
        return 'proximo';
    }

    return 'cola';
}

function selectDay(dayNumber: number): void {
    router.get(
        basePath,
        { day: dayNumber },
        { preserveState: true, preserveScroll: true },
    );
}

function selectEntry(entry: TournamentQueueEntry): void {
    router.get(
        basePath,
        { entry: entry.registration_id },
        { preserveState: true, preserveScroll: true },
    );
}

/** Arranca el día y el lazo del vaquero que está en salida, sin salir de la consola. */
function startDay(entry: TournamentQueueEntry): void {
    router.put(`${basePath}/days/${entry.day.id}/start`, undefined, {
        preserveScroll: true,
    });
}

function startRound(entry: TournamentQueueEntry): void {
    router.put(`${basePath}/rounds/${entry.round.id}/start`, undefined, {
        preserveScroll: true,
    });
}

/** Pone en marcha la jornada completa: el día en todas las categorías. */
function startAllDays(): void {
    if (props.selectedDayNumber === null) {
        return;
    }

    router.put(
        `${basePath}/start-all-days`,
        { day: props.selectedDayNumber },
        { preserveScroll: true },
    );
}

/**
 * Tiempo real: la consola escucha el torneo completo, así que cualquier
 * resultado de cualquiera de sus categorías refresca la cola de salida.
 */
const liveChannel = echo.private(`tournament.${props.tournament.id}`);

const LIVE_EVENTS = [
    '.round-result.recorded',
    '.round-result.updated',
    '.round.started',
    '.round.completed',
    '.competition-day.started',
    '.competition-day.completed',
] as const;

let refreshTimer: ReturnType<typeof setTimeout> | null = null;

function refreshFromLive(): void {
    if (refreshTimer !== null) {
        clearTimeout(refreshTimer);
    }

    refreshTimer = setTimeout(() => {
        router.reload({
            only: [
                'days',
                'selectedDayNumber',
                'queue',
                'currentEntry',
                'standings',
            ],
        });
    }, 150);
}

onMounted(() => {
    LIVE_EVENTS.forEach((event) => liveChannel.listen(event, refreshFromLive));
});

onBeforeUnmount(() => {
    if (refreshTimer !== null) {
        clearTimeout(refreshTimer);
    }

    LIVE_EVENTS.forEach((event) =>
        liveChannel.stopListening(event, refreshFromLive),
    );
});

/** Captura y corrección de la entrada que está en el panel central. */
const time = ref(props.currentEntry?.result?.time ?? '');
const penalty = ref(penaltyOf(props.currentEntry?.result ?? null));
const successful = ref(props.currentEntry?.result?.successful ?? true);
const notes = ref(props.currentEntry?.result?.notes ?? '');

/** La penalización solo se escribe si la hubo: cero llega como campo vacío. */
function penaltyOf(result: EntryResult | null): string {
    return result === null || result.penalty_ms === 0 ? '' : result.penalty;
}

// Al cambiar la entrada seleccionada se resincroniza el formulario.
watch(
    () => props.currentEntry?.registration_id,
    () => {
        time.value = props.currentEntry?.result?.time ?? '';
        penalty.value = penaltyOf(props.currentEntry?.result ?? null);
        successful.value = props.currentEntry?.result?.successful ?? true;
        notes.value = props.currentEntry?.result?.notes ?? '';
    },
);

const form = useForm({
    registration_id: props.currentEntry?.registration_id ?? 0,
    time: '',
    penalty: '',
    successful: true,
    notes: null as string | null,
});

const canEdit = computed(
    () =>
        props.currentEntry !== null &&
        props.currentEntry.round.accepts_results,
);

function save(): void {
    if (props.currentEntry === null) {
        return;
    }

    form.registration_id = props.currentEntry.registration_id;
    form.time = successful.value ? normalizeTimeInput(time.value) : '';
    form.penalty = successful.value ? normalizeTimeInput(penalty.value) : '';
    form.successful = successful.value;
    form.notes = notes.value || null;

    const options = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) =>
            toast.error(
                Object.values(errors)[0] ??
                    'No se pudo guardar el resultado.',
            ),
    };

    if (props.currentEntry.result !== null) {
        form.put(`${basePath}/results/${props.currentEntry.result!.id}`, options);

        return;
    }

    form.post(`${basePath}/results`, options);
}

function markFailed(): void {
    successful.value = false;
    time.value = '';
    penalty.value = '';
    save();
}
</script>

<template>
    <Head :title="`Consola · ${tournament.name}`" />

    <div class="p-4">
        <PageHeader
            :eyebrow="tournament.name"
            title="Consola del evento"
            description="La cola de salida de todas las categorías, el vaquero en pista y el que le sigue. Los tiempos se capturan en segundos."
        >
            <template #actions>
                <StatusBadge
                    :status="tournament.status"
                    :label="tournament.status_label"
                />
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/admin/tournaments/${tournament.slug}`">
                        Volver al evento
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-sm text-muted-foreground">Día:</span>
            <Button
                v-for="day in days"
                :key="day.day_number"
                size="sm"
                :variant="
                    day.day_number === selectedDayNumber ? 'default' : 'outline'
                "
                :title="`${day.status_label} · ${day.rounds_count} lazos`"
                @click="selectDay(day.day_number)"
            >
                Día {{ day.day_number }}
            </Button>
            <Button
                v-if="selectedDay?.status === 'pending'"
                variant="outline"
                size="sm"
                @click="startAllDays"
            >
                <Flag class="size-4" />
                Iniciar día · todas las categorías
            </Button>
            <p v-if="days.length === 0" class="text-sm text-muted-foreground">
                La estructura de días se crea desde cada categoría.
            </p>
        </div>

        <div class="grid gap-4 xl:grid-cols-[300px_minmax(0,1fr)_340px]">
            <!-- Cola de salida -->
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="text-base">Cola de salida</CardTitle>
                    <p class="text-xs text-muted-foreground">
                        Quién está en pista, quién sigue y el orden completo.
                    </p>
                </CardHeader>
                <CardContent class="p-0">
                    <EmptyState
                        v-if="queue.length === 0"
                        class="px-4 pb-4"
                        title="Cola vacía"
                        description="No hay rondas pendientes para este día."
                    />
                    <ul
                        v-else
                        class="max-h-[560px] divide-y overflow-y-auto"
                    >
                        <li
                            v-for="entry in queue"
                            :key="entry.registration_id"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors hover:bg-muted"
                                :class="{
                                    'bg-emerald-500/5 hover:bg-emerald-500/10':
                                        roleOf(entry) === 'salida',
                                }"
                                @click="selectEntry(entry)"
                            >
                                <span class="w-24 shrink-0">
                                    <Badge
                                        v-if="roleOf(entry) === 'salida'"
                                        class="border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-400"
                                    >
                                        <span class="mr-1.5 inline-block size-1.5 rounded-full bg-current" />
                                        En salida
                                    </Badge>
                                    <Badge
                                        v-else-if="roleOf(entry) === 'proximo'"
                                        class="border-transparent bg-amber-500/15 text-amber-700 dark:text-amber-400"
                                    >
                                        Próximo
                                    </Badge>
                                    <span
                                        v-else
                                        class="font-mono text-sm tabular-nums text-muted-foreground"
                                    >
                                        {{ entry.sequence }}.
                                    </span>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium"
                                    >
                                        {{ entry.participant.full_name }}
                                    </span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                    >
                                        #{{ entry.registration_number }} ·
                                        {{ entry.category.name }} ·
                                        {{ roundLabel(entry.round) }}
                                        <template v-if="entry.group">
                                            · {{ entry.group.name }}
                                        </template>
                                    </span>
                                </span>

                                <span
                                    v-if="entry.result"
                                    class="shrink-0 font-mono text-xs tabular-nums"
                                    :class="
                                        entry.result.successful
                                            ? 'text-muted-foreground'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{
                                        entry.result.successful
                                            ? entry.result.total
                                            : '✗'
                                    }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- En salida y el próximo -->
            <div class="space-y-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-2 space-y-0"
                    >
                        <div>
                            <CardTitle class="text-base">
                                <template v-if="currentEntry">
                                    En salida ·
                                    {{ currentEntry.sequence }}
                                </template>
                                <template v-else>
                                    Sin salidas pendientes
                                </template>
                            </CardTitle>
                            <p
                                v-if="currentEntry"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ currentEntry.category.name }} ·
                                Día {{ currentEntry.day.day_number }} ·
                                {{ roundLabel(currentEntry.round) }}
                            </p>
                        </div>

                        <div
                            v-if="currentEntry"
                            class="flex flex-col items-end gap-1"
                        >
                            <StatusBadge
                                :status="currentEntry.round.status"
                                :label="currentEntry.round.status_label"
                            />
                            <span
                                v-if="currentEntry.group"
                                class="text-xs text-muted-foreground"
                            >
                                Grupo {{ currentEntry.group.name }}
                            </span>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <EmptyState
                            v-if="currentEntry === null"
                            title="Todo el mundo salió"
                            description="Cuando queden rondas pendientes aparecerá aquí el siguiente vaquero."
                        />

                        <template v-else>
                            <div
                                class="mb-4 flex items-center justify-between gap-3"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <ParticipantAvatar
                                        :name="currentEntry.participant.full_name"
                                        :photo-url="
                                            currentEntry.participant.photo_url
                                        "
                                        class="size-12 shrink-0"
                                    />
                                    <div class="min-w-0">
                                        <p class="truncate text-lg font-semibold">
                                            {{
                                                currentEntry.participant
                                                    .full_name
                                            }}
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            #{{ currentEntry.registration_number }}
                                            <template v-if="currentEntry.horse">
                                                · {{ currentEntry.horse }}
                                            </template>
                                        </p>
                                    </div>
                                </div>

                                <span
                                    v-if="currentEntry.result"
                                    class="font-mono text-sm tabular-nums"
                                    :class="
                                        currentEntry.result.successful
                                            ? ''
                                            : 'text-muted-foreground line-through'
                                    "
                                >
                                    {{
                                        currentEntry.result.successful
                                            ? currentEntry.result.total
                                            : 'Fallido'
                                    }}
                                </span>
                            </div>

                            <div class="mb-4 grid gap-2 sm:grid-cols-2">
                                <div
                                    class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                >
                                    <span class="text-sm">
                                        Día
                                        {{ currentEntry.day.day_number }}
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            · {{ currentEntry.day.status_label }}
                                        </span>
                                    </span>
                                    <Button
                                        v-if="currentEntry.day.status === 'pending'"
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        :disabled="form.processing"
                                        @click="startDay(currentEntry)"
                                    >
                                        <Play class="size-4" />
                                        Iniciar día
                                    </Button>
                                </div>

                                <div
                                    class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                >
                                    <span class="text-sm">
                                        {{ roundLabel(currentEntry.round) }}
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            · {{
                                                currentEntry.round.status_label
                                            }}
                                        </span>
                                    </span>
                                    <Button
                                        v-if="
                                            currentEntry.round.status === 'pending'
                                        "
                                        type="button"
                                        size="sm"
                                        :disabled="form.processing"
                                        @click="startRound(currentEntry)"
                                    >
                                        <Flag class="size-4" />
                                        Iniciar lazo
                                    </Button>
                                </div>
                            </div>

                            <form class="grid gap-4" @submit.prevent="save">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="time">Tiempo (s)</Label>
                                        <Input
                                            id="time"
                                            v-model="time"
                                            :disabled="!canEdit || !successful"
                                            inputmode="decimal"
                                            placeholder="8.342"
                                            class="font-mono tabular-nums"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="penalty">
                                            Penalización (s)
                                        </Label>
                                        <Input
                                            id="penalty"
                                            v-model="penalty"
                                            :disabled="!canEdit || !successful"
                                            inputmode="decimal"
                                            placeholder="0.000"
                                            class="font-mono tabular-nums"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label>Resultado</Label>
                                    <div class="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="flex-1"
                                            :class="
                                                successful
                                                    ? 'border-primary text-primary'
                                                    : ''
                                            "
                                            :disabled="!canEdit"
                                            @click="successful = true"
                                        >
                                            <Check class="size-4" />
                                            Lazo acertado
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            class="flex-1"
                                            :variant="
                                                !successful
                                                    ? 'destructive'
                                                    : 'outline'
                                            "
                                            :disabled="!canEdit"
                                            @click="markFailed"
                                        >
                                            <X class="size-4" />
                                            Lazo fallido
                                        </Button>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="notes">Observaciones</Label>
                                    <textarea
                                        id="notes"
                                        v-model="notes"
                                        :disabled="!canEdit"
                                        rows="2"
                                        placeholder="Opcional: caída, derribo, etc."
                                        class="border-input placeholder:text-muted-foreground focus-visible:ring-ring/50 focus-visible:border-ring w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                </div>

                                <p
                                    v-if="!currentEntry.round.accepts_results"
                                    class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                                >
                                    El lazo está en estado
                                    «{{ currentEntry.round.status_label }}».
                                </p>

                                <Button
                                    type="submit"
                                    :disabled="!canEdit || form.processing"
                                >
                                    <Play class="size-4" />
                                    {{
                                        currentEntry.result
                                            ? 'Corregir resultado'
                                            : 'Registrar tiempo'
                                    }}
                                </Button>
                            </form>
                        </template>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">El próximo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EmptyState
                            v-if="nextUp === null"
                            title="Nadie espera"
                            description="Cuando queden rondas pendientes aparecerá aquí el siguiente vaquero."
                        />
                        <template v-else>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <ParticipantAvatar
                                        :name="nextUp.participant.full_name"
                                        :photo-url="
                                            nextUp.participant.photo_url
                                        "
                                        class="size-12 shrink-0"
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-base font-semibold"
                                        >
                                            {{
                                                nextUp.participant.full_name
                                            }}
                                        </p>
                                        <p
                                            class="truncate text-sm text-muted-foreground"
                                        >
                                            #{{
                                                nextUp.registration_number
                                            }}
                                            <template v-if="nextUp.horse">
                                                · {{ nextUp.horse }}
                                            </template>
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ nextUp.category.name }} ·
                                            {{ roundLabel(nextUp.round) }}
                                            <template v-if="nextUp.group">
                                                · {{ nextUp.group.name }}
                                            </template>
                                        </p>
                                    </div>
                                </div>

                                <Button
                                    size="sm"
                                    :disabled="nextUp.round.accepts_results === false"
                                    @click="selectEntry(nextUp)"
                                >
                                    <ArrowRight class="size-4" />
                                    Llamar
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </div>

            <!-- Clasificación -->
            <div class="space-y-4">
                <Card
                    v-for="categoryStandings in standings"
                    :key="categoryStandings.id"
                >
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{ categoryStandings.name }}
                        </CardTitle>
                        <p class="text-xs text-muted-foreground">
                            {{ categoryStandings.status_label }}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <LeaderboardTable
                            :standings="categoryStandings.standings"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
