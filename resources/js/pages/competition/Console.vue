<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Flag, Play, Square } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import echo from '@/echo';
import LeaderboardTable from '@/modules/competition/components/LeaderboardTable.vue';
import TimeEntryRow from '@/modules/competition/components/TimeEntryRow.vue';
import type {
    ActiveRound,
    CompetitionDay,
    ConsoleEntry,
    StandingRow,
} from '@/modules/competition/types';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import { roundLabel } from '@/modules/shared/format';
import type { TournamentCategory } from '@/modules/tournaments/types';

/**
 * La pantalla que el administrador tiene abierta mientras se corre la
 * categoría: estructura de días y rondas a la izquierda, captura de tiempos
 * en el centro, clasificación a la derecha actualizándose sola.
 */
const props = defineProps<{
    category: TournamentCategory;
    days: { data: CompetitionDay[] };
    activeRound: ActiveRound | null;
    entries: ConsoleEntry[];
    standings: { data: StandingRow[] };
}>();

const tournamentSlug = props.category.tournament!.slug;
const basePath = `/admin/tournaments/${tournamentSlug}/categories/${props.category.slug}`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Eventos', href: '/admin/tournaments' }],
    },
});

function selectRound(roundId: number): void {
    router.get(
        `${basePath}/competition`,
        { round: roundId },
        { preserveState: true, preserveScroll: true },
    );
}

/**
 * Sin jinetes activos no se arranca nada: el motor lo rechaza igual, pero la
 * consola no ofrece el botón para no dejar intentarlo en plena pista.
 */
const hasCompetitors = computed(() =>
    props.entries.some((entry) => entry.accepts_results),
);

function act(path: string): void {
    router.put(
        `${basePath}/${path}`,
        {},
        {
            preserveScroll: true,
            // La consola no tiene dónde pintar errores de formulario: lo que
            // el dominio rechace se avisa como aviso flotante.
            onError: (errors) => {
                const [message] = Object.values(errors);

                toast.error(message ?? 'No se pudo completar la acción.');
            },
        },
    );
}

/**
 * Tiempo real: la consola escucha la categoría y refresca estructura, ronda
 * activa, captura y clasificación cuando otro operador registra resultados.
 * Se agrupa la ráfaga de eventos en una sola recarga.
 */
const liveChannel = echo.private(`category.${props.category.id}`);

/**
 * Los nombres llegan con `.` al inicio para que el formateador de Echo no les
 * aplique el namespace `App\Events\...`: los eventos usan `broadcastAs()`, así
 * que en el wire llegan tal cual (`round-result.recorded`).
 */
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
            only: ['days', 'activeRound', 'entries', 'standings'],
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
</script>

<template>
    <Head :title="`Consola · ${category.name}`" />

    <div class="p-4">
        <PageHeader
            :eyebrow="category.tournament?.name"
            :title="`Consola de competencia · ${category.name}`"
            description="Los tiempos se capturan en segundos y se guardan como milisegundos enteros. La clasificación se recalcula sola."
        >
            <template #actions>
                <StatusBadge
                    :status="category.status"
                    :label="category.status_label"
                />
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`${basePath}/registrations`"
                        >Inscripciones</Link
                    >
                </Button>
                <Button
                    v-if="category.status !== 'finished'"
                    variant="outline"
                    size="sm"
                    @click="act('complete')"
                >
                    <Flag class="size-4" />
                    Finalizar categoría
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)_320px]">
            <!-- Estructura de días y lazos -->
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="text-base">Días y lazos</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="day in days.data"
                        :key="day.id"
                        class="space-y-2"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">
                                    Día {{ day.day_number }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ day.date ?? 'Sin fecha' }}
                                </p>
                            </div>
                            <StatusBadge
                                :status="day.status"
                                :label="day.status_label"
                            />
                        </div>

                        <div class="grid grid-cols-4 gap-1">
                            <button
                                v-for="round in day.rounds"
                                :key="round.id"
                                type="button"
                                class="rounded-md border px-2 py-1.5 text-xs transition-colors"
                                :class="[
                                    activeRound?.id === round.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'hover:bg-muted',
                                    round.status === 'active' &&
                                    activeRound?.id !== round.id
                                        ? 'border-emerald-500/50'
                                        : '',
                                ]"
                                :title="`${roundLabel(round)} · ${round.status_label}`"
                                @click="selectRound(round.id)"
                            >
                                {{
                                    round.is_final
                                        ? 'Final'
                                        : `R${round.round_number}`
                                }}
                            </button>
                        </div>

                        <div class="flex gap-1">
                            <Button
                                v-if="day.status === 'pending'"
                                size="sm"
                                variant="outline"
                                class="flex-1"
                                :disabled="!hasCompetitors"
                                :title="
                                    hasCompetitors
                                        ? undefined
                                        : 'La categoría no tiene vaqueros inscritos.'
                                "
                                @click="act(`days/${day.id}/start`)"
                            >
                                <Play class="size-3.5" />
                                Iniciar día
                            </Button>
                            <Button
                                v-else-if="day.status === 'active'"
                                size="sm"
                                variant="outline"
                                class="flex-1"
                                @click="act(`days/${day.id}/complete`)"
                            >
                                <CheckCircle2 class="size-3.5" />
                                Finalizar día
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Captura de tiempos -->
            <Card>
                <CardHeader
                    class="flex flex-row items-start justify-between gap-2 space-y-0"
                >
                    <div>
                        <CardTitle class="text-base">
                            <template v-if="activeRound">
                                Día {{ activeRound.day_number }} ·
                                {{ roundLabel(activeRound) }}
                            </template>
                            <template v-else>Sin lazo seleccionado</template>
                        </CardTitle>
                        <p
                            v-if="activeRound"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ activeRound.status_label }}
                        </p>
                    </div>

                    <div v-if="activeRound" class="flex gap-1">
                        <Button
                            v-if="activeRound.status === 'pending'"
                            size="sm"
                            :disabled="!hasCompetitors"
                            :title="
                                hasCompetitors
                                    ? undefined
                                    : 'La categoría no tiene vaqueros inscritos.'
                            "
                            @click="act(`rounds/${activeRound.id}/start`)"
                        >
                            <Play class="size-3.5" />
                            Iniciar lazo
                        </Button>
                        <Button
                            v-else-if="activeRound.status === 'active'"
                            size="sm"
                            variant="outline"
                            @click="act(`rounds/${activeRound.id}/complete`)"
                        >
                            <Square class="size-3.5" />
                            Finalizar lazo
                        </Button>
                    </div>
                </CardHeader>

                <CardContent>
                    <EmptyState
                        v-if="entries.length === 0"
                        title="Sin inscritos en esta categoría"
                        description="Inscriba vaqueros antes de empezar a cronometrar."
                    >
                        <Button variant="outline" as-child>
                            <Link :href="`${basePath}/registrations`">
                                Ir a inscripciones
                            </Link>
                        </Button>
                    </EmptyState>

                    <p
                        v-else-if="activeRound && !activeRound.accepts_results"
                        class="mb-4 rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                    >
                        El lazo está en estado «{{ activeRound.status_label }}».
                        Inicie el lazo para poder registrar tiempos.
                    </p>

                    <div v-if="entries.length > 0" class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead class="text-left text-muted-foreground">
                                <tr class="border-b">
                                    <th class="pb-2 font-medium">#</th>
                                    <th class="pb-2 font-medium">Vaquero</th>
                                    <th class="pb-2 font-medium">Tiempo (s)</th>
                                    <th class="pb-2 font-medium">Penal. (s)</th>
                                    <th class="pb-2 font-medium">Resultado</th>
                                    <th class="pb-2 font-medium">Registrado</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <TimeEntryRow
                                    v-for="entry in entries"
                                    :key="entry.registration_id"
                                    :entry="entry"
                                    :base-path="basePath"
                                    :round-id="activeRound?.id ?? 0"
                                    :editable="
                                        (activeRound?.accepts_results ??
                                            false) &&
                                        entry.accepts_results
                                    "
                                />
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Clasificación -->
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="text-base">Clasificación</CardTitle>
                    <p class="text-xs text-muted-foreground">
                        Más lazos acertados primero; entre iguales, menor
                        tiempo.
                    </p>
                </CardHeader>
                <CardContent>
                    <LeaderboardTable :standings="standings.data" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
