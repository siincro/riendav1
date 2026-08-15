<script setup lang="ts">
/**
 * Encabezado de una pantalla del panel: título, contexto y acciones.
 */
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <header
        class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="space-y-1">
            <p
                v-if="eyebrow"
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
