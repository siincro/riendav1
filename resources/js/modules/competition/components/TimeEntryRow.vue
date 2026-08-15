<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ConsoleEntry } from '@/modules/competition/types';
import { normalizeTimeInput } from '@/modules/shared/format';

/**
 * Una fila de captura de la consola de competencia.
 *
 * El administrador escribe el tiempo en segundos ("8.342"), la penalización
 * que corresponda ("5.000") o marca el lazo como fallido; el backend lo
 * convierte a milisegundos enteros. Si el inscrito ya tiene resultado, la
 * misma fila sirve para corregirlo.
 *
 * Lo que suma al acumulado es tiempo + penalización, así que la columna de
 * registro muestra ese total y, debajo, el desglose cuando hubo castigo.
 */
const props = defineProps<{
    entry: ConsoleEntry;
    basePath: string;
    roundId: number;
    editable: boolean;
}>();

const time = ref(props.entry.result?.time ?? '');
const penalty = ref(penaltyOf(props.entry.result));
const successful = ref(props.entry.result?.successful ?? true);

/** La penalización solo se escribe si la hubo: cero llega como campo vacío. */
function penaltyOf(result: ConsoleEntry['result']): string {
    return result === null || result.penalty_ms === 0 ? '' : result.penalty;
}

// Al cambiar de ronda la fila se reutiliza: se resincroniza con el resultado.
watch(
    () => props.entry.result,
    (result) => {
        time.value = result?.time ?? '';
        penalty.value = penaltyOf(result ?? null);
        successful.value = result?.successful ?? true;
    },
);

const form = useForm({
    registration_id: props.entry.registration_id,
    time: '',
    penalty: '',
    successful: true,
    notes: null as string | null,
});

function save(): void {
    form.time = successful.value ? normalizeTimeInput(time.value) : '';
    form.penalty = successful.value ? normalizeTimeInput(penalty.value) : '';
    form.successful = successful.value;

    const isCorrection = props.entry.result !== null;

    if (isCorrection) {
        form.put(`${props.basePath}/results/${props.entry.result!.id}`, {
            preserveScroll: true,
        });

        return;
    }

    form.post(`${props.basePath}/rounds/${props.roundId}/results`, {
        preserveScroll: true,
    });
}

function markFailed(): void {
    successful.value = false;
    time.value = '';
    penalty.value = '';
    save();
}
</script>

<template>
    <tr :class="entry.result ? 'bg-muted/20' : ''">
        <td class="py-2 pr-3 font-mono tabular-nums">
            {{ entry.registration_number }}
        </td>

        <td class="py-2 pr-3">
            <p class="font-medium">{{ entry.participant.full_name }}</p>
            <p v-if="entry.horse" class="text-xs text-muted-foreground">
                {{ entry.horse }}
            </p>
        </td>

        <td class="py-2 pr-3">
            <Input
                v-model="time"
                :disabled="!editable || !successful"
                inputmode="decimal"
                placeholder="8.342"
                class="w-28 text-right font-mono tabular-nums"
                @keyup.enter="save"
            />
        </td>

        <td class="py-2 pr-3">
            <Input
                v-model="penalty"
                :disabled="!editable || !successful"
                inputmode="decimal"
                placeholder="0.000"
                class="w-24 text-right font-mono tabular-nums"
                @keyup.enter="save"
            />
        </td>

        <td class="py-2 pr-3">
            <div class="flex items-center gap-1">
                <Button
                    type="button"
                    size="sm"
                    :variant="successful ? 'default' : 'outline'"
                    :disabled="!editable"
                    title="Lazo acertado"
                    @click="successful = true"
                >
                    <Check class="size-4" />
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="!successful ? 'destructive' : 'outline'"
                    :disabled="!editable"
                    title="Lazo fallido"
                    @click="markFailed"
                >
                    <X class="size-4" />
                </Button>
            </div>
        </td>

        <td class="py-2 pr-3">
            <span
                v-if="entry.result"
                class="font-mono text-sm tabular-nums"
                :class="
                    entry.result.successful
                        ? ''
                        : 'text-muted-foreground line-through'
                "
            >
                {{ entry.result.successful ? entry.result.total : 'Fallido' }}
            </span>
            <span
                v-if="entry.result?.successful && entry.result.penalty_ms > 0"
                class="block font-mono text-xs text-muted-foreground tabular-nums"
            >
                {{ entry.result.time }} + {{ entry.result.penalty }}
            </span>
            <span v-else class="text-xs text-muted-foreground"
                >Sin registrar</span
            >
        </td>

        <td class="py-2 text-right">
            <Button
                size="sm"
                :variant="entry.result ? 'outline' : 'default'"
                :disabled="!editable || form.processing"
                @click="save"
            >
                {{ entry.result ? 'Corregir' : 'Registrar' }}
            </Button>
        </td>
    </tr>
</template>
