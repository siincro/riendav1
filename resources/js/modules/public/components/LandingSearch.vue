<script setup lang="ts">
import { Search } from '@lucide/vue';

/**
 * El buscador del cartel: una píldora de cuero que se enciende en fuego al
 * recibir el foco. Lo comparten los directorios de torneos y de jinetes.
 */
defineProps<{
    placeholder: string;
}>();

const value = defineModel<string>({ required: true });
</script>

<template>
    <div class="relative max-w-md">
        <Search
            class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-rienda-sombra"
        />
        <input
            v-model="value"
            type="search"
            :placeholder="placeholder"
            class="w-full rounded-full border border-rienda-crema/20 bg-rienda-tierra py-2.5 pr-4 pl-11 font-condensed text-base tracking-[0.08em] text-rienda-crema placeholder:text-rienda-sombra focus:border-rienda-fuego focus:outline-none"
        />
    </div>
</template>
