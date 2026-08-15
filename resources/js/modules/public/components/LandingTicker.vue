<script setup lang="ts">
import { computed } from 'vue';

/**
 * La cinta que corre sobre la cabecera de la landing.
 *
 * El contenido se duplica para que el desplazamiento cierre el ciclo sin salto
 * (la animación recorre exactamente la mitad del ancho). Es decoración: se
 * oculta a los lectores de pantalla y se detiene si el sistema pide menos
 * movimiento.
 */
const props = defineProps<{
    items: string[];
}>();

const loop = computed(() => [...props.items, ...props.items]);
</script>

<template>
    <div
        v-if="items.length > 0"
        aria-hidden="true"
        class="overflow-hidden border-b border-rienda-crema/8 bg-rienda-carbon py-2.5"
    >
        <div
            class="flex w-max animate-deslizar gap-11 font-condensed text-[15px] tracking-[0.09em] whitespace-nowrap text-rienda-arena uppercase motion-reduce:animate-none"
        >
            <span
                v-for="(item, index) in loop"
                :key="`${index}-${item}`"
                class="flex items-center gap-2.5"
            >
                <span class="size-[5px] rounded-full bg-rienda-fuego" />
                {{ item }}
            </span>
        </div>
    </div>
</template>
