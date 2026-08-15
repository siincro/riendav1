<script setup lang="ts">
import type { StandingRow } from '@/modules/competition/types';
import EmptyState from '@/modules/shared/components/EmptyState.vue';

/**
 * La clasificación de la categoría.
 *
 * El orden es el del deporte: primero manda la cantidad de rondas acertadas,
 * y solo entre iguales decide el menor tiempo acumulado. Los empates comparten
 * posición — no se inventa un tercer criterio de desempate.
 */
defineProps<{
    standings: StandingRow[];
}>();
</script>

<template>
    <EmptyState
        v-if="standings.length === 0"
        title="Sin clasificación todavía"
        description="La tabla se construye sola a medida que se registran los tiempos."
    />

    <table v-else class="w-full text-sm">
        <thead class="text-left text-muted-foreground">
            <tr class="border-b">
                <th class="py-2 font-medium">#</th>
                <th class="py-2 font-medium">Vaquero</th>
                <th class="py-2 text-right font-medium">Acertadas</th>
                <th class="py-2 text-right font-medium">Tiempo</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <tr
                v-for="row in standings"
                :key="row.registration.id"
                :class="row.position === 1 ? 'bg-amber-500/5' : ''"
            >
                <td class="py-2.5">
                    <span class="font-semibold tabular-nums">
                        {{ row.position ?? '—' }}
                    </span>
                    <span
                        v-if="row.tied"
                        class="ml-1 text-xs text-muted-foreground"
                        title="Posición compartida"
                    >
                        =
                    </span>
                </td>
                <td class="py-2.5">
                    <p class="font-medium">
                        {{ row.registration.participant.full_name }}
                    </p>
                    <p
                        v-if="row.registration.horse"
                        class="text-xs text-muted-foreground"
                    >
                        {{ row.registration.horse }}
                    </p>
                </td>
                <td class="py-2.5 text-right font-semibold tabular-nums">
                    {{ row.total_successful_rounds }}
                </td>
                <td class="py-2.5 text-right font-mono tabular-nums">
                    {{ row.total_time_formatted }}
                    <!-- El acumulado ya incluye el castigo: se desglosa. -->
                    <span
                        v-if="row.total_penalty_ms > 0"
                        class="block text-xs font-normal text-muted-foreground"
                    >
                        +{{ row.total_penalty_formatted }} penal.
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</template>
