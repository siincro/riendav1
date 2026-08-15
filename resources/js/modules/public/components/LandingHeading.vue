<script setup lang="ts">
/**
 * El encabezado de sección de la landing: un antetítulo condensado en color
 * fuego sobre un titular de cartel. Lo comparten todas las secciones para que
 * la página se lea como una sola pieza.
 */
defineProps<{
    kicker: string;
    title: string;
}>();
</script>

<template>
    <div>
        <p
            class="font-condensed text-[15px] tracking-[0.22em] text-rienda-fuego uppercase"
        >
            {{ kicker }}
        </p>
        <h2
            class="mt-2.5 font-display text-[clamp(2.25rem,4.4vw,3.875rem)] leading-[0.98] text-rienda-crema uppercase"
        >
            {{ title }}
        </h2>
    </div>
</template>
