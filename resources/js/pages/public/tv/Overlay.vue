<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import echo from '@/echo';
import type { PublicStandingRow } from '@/modules/competition/types';

/**
 * RIENDA TV: la tabla de clasificación de una categoría como overlay para
 * OBS Studio (fuente de navegador). Es una página desnuda: sin layout, con
 * fondo transparente y panel negro traslúcido, lista para superponer sobre
 * la señal de video. Se actualiza sola escuchando el canal público de Reverb.
 */
type OverlayProps = {
    tournament: { name: string; slug: string } | null;
    category: { slug: string; name: string } | null;
    standings: { data: PublicStandingRow[] };
};

const props = defineProps<OverlayProps>();

/**
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

/** El jinete cuyo resultado acaba de registrarse: se resalta ~3 segundos. */
const highlightedParticipant = ref<string | null>(null);

const liveChannelName = computed(() => {
    if (props.tournament === null || props.category === null) {
        return null;
    }

    return `public-category.${props.tournament.slug}.${props.category.slug}`;
});

let liveChannel: ReturnType<typeof echo.channel> | null = null;
let reloadTimer: ReturnType<typeof setTimeout> | null = null;
let highlightTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * Los eventos de resultado traen al `participant` que acaba de puntuar; los de
 * ronda y día no. Se resalta la fila y siempre se refresca la clasificación.
 */
function refreshStandings(payload?: { participant?: string }): void {
    if (payload?.participant !== undefined) {
        highlight(payload.participant);
    }

    if (reloadTimer !== null) {
        clearTimeout(reloadTimer);
    }

    reloadTimer = setTimeout(() => {
        router.reload({ only: ['standings'] });
    }, 120);
}

function highlight(participant: string): void {
    if (highlightTimer !== null) {
        clearTimeout(highlightTimer);
    }

    highlightedParticipant.value = participant;
    highlightTimer = setTimeout(() => {
        highlightedParticipant.value = null;
        highlightTimer = null;
    }, 3000);
}

function unsubscribeLive(): void {
    if (liveChannel === null) {
        return;
    }

    LIVE_EVENTS.forEach((event) =>
        liveChannel?.stopListening(event, refreshStandings),
    );
    liveChannel = null;
}

function subscribeLive(): void {
    if (liveChannelName.value === null) {
        return;
    }

    liveChannel = echo.channel(liveChannelName.value);
    LIVE_EVENTS.forEach((event) =>
        liveChannel?.listen(event, refreshStandings),
    );
}

watch(liveChannelName, (channelName, previousChannelName) => {
    if (channelName === previousChannelName) {
        return;
    }

    unsubscribeLive();
    subscribeLive();
});

onMounted(subscribeLive);

onBeforeUnmount(() => {
    if (reloadTimer !== null) {
        clearTimeout(reloadTimer);
    }

    if (highlightTimer !== null) {
        clearTimeout(highlightTimer);
    }

    unsubscribeLive();
});
</script>

<template>
    <Head title="RIENDA TV" />

    <main class="overlay">
        <div v-if="category === null" class="overlay-idle">
            Sin categoría activa
        </div>

        <section v-else class="panel">
            <header class="panel-header">
                <p class="panel-label">Clasificación</p>
                <h1 class="panel-title">{{ category.name }}</h1>
                <p v-if="tournament" class="panel-subtitle">
                    {{ tournament.name }}
                </p>
            </header>

            <div class="panel-body">
                <div v-if="standings.data.length === 0" class="panel-empty">
                    Aún no hay tiempos registrados
                </div>

                <table v-else class="panel-table">
                    <thead>
                        <tr>
                            <th class="col-pos">#</th>
                            <th>Vaquero</th>
                            <th class="col-metric text-right">Lazos</th>
                            <th class="col-time text-right">Tiempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="standing in standings.data"
                            :key="standing.registration.registration_number"
                            :class="{
                                highlight:
                                    standing.registration.participant
                                        .public_id === highlightedParticipant,
                            }"
                        >
                            <td class="col-pos">
                                <span
                                    v-if="standing.position === 1"
                                    class="pos-1"
                                >
                                    {{ standing.position ?? '—' }}
                                </span>
                                <span v-else class="text-white/60 tabular-nums">
                                    {{ standing.position ?? '—' }}
                                </span>
                            </td>
                            <td class="name">
                                {{
                                    standing.registration.participant.full_name
                                }}
                            </td>
                            <td class="col-metric text-right tabular-nums">
                                {{ standing.total_successful_rounds }}
                            </td>
                            <td
                                class="col-time text-right font-mono tabular-nums"
                            >
                                {{ standing.total_time_formatted }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>

<style>
html,
body,
#app {
    height: 100%;
    overflow: hidden;
    background: transparent !important;
    color: #fff;
    font-family: var(--font-sans, ui-sans-serif, system-ui, sans-serif);
}

/* La barra de progreso de Inertia no debe aparecer en la transmisión. */
#nprogress {
    display: none !important;
}

.overlay {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    box-sizing: border-box;
    height: 100%;
    width: 100vw;
    padding: 12px;
    overflow: hidden;
}

.overlay-idle {
    margin-top: 24px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.panel {
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    width: 100%;
    max-width: 520px;
    height: 100%;
    max-height: 100%;
    background: rgba(0, 0, 0, 0.86);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    overflow: hidden;
}

.panel-header {
    flex-shrink: 0;
    padding: 14px 16px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.panel-label {
    margin: 0;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #f59e0b;
}

.panel-title {
    margin: 2px 0 0;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.1;
}

.panel-subtitle {
    margin: 4px 0 0;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.55);
}

.panel-body {
    flex: 1 1 auto;
    overflow-y: auto;
    scrollbar-width: none;
}

.panel-body::-webkit-scrollbar {
    display: none;
}

.panel-empty {
    padding: 24px 16px;
    text-align: center;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.6);
}

.panel-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 14px;
}

.panel-table thead th {
    padding: 8px 12px;
    text-align: left;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.45);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.panel-table td {
    padding: 8px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.panel-table tbody tr:last-child td {
    border-bottom: none;
}

.panel-table th.col-pos,
.panel-table td.col-pos {
    width: 44px;
    text-align: center;
}

.panel-table th.col-metric,
.panel-table td.col-metric {
    width: 64px;
}

.panel-table th.col-time,
.panel-table td.col-time {
    width: 92px;
}

.panel-table td.name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 500;
}

.panel-table tbody tr.highlight {
    background: linear-gradient(
        90deg,
        rgba(245, 158, 11, 0.22),
        rgba(245, 158, 11, 0.06)
    );
    box-shadow: inset 3px 0 0 #f59e0b;
    animation: row-flash 0.3s ease;
}

@keyframes row-flash {
    from {
        background-color: rgba(245, 158, 11, 0.45);
    }
    to {
        background-color: rgba(245, 158, 11, 0.1);
    }
}

.pos-1 {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 9999px;
    background: rgba(245, 158, 11, 0.18);
    color: #fbbf24;
    font-weight: 700;
}
</style>
