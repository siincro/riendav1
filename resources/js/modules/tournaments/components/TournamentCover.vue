<script setup lang="ts">
import { Trophy } from '@lucide/vue';
import { ref, watch } from 'vue';

/**
 * La portada de un torneo.
 *
 * La mayoría de los torneos se cargan sin foto, así que el vacío es el caso
 * normal, no un error: en vez de un hueco roto se pinta un marcador con la
 * copa, en el tono del sitio donde esté —neutro en el panel, de cuero en la
 * cara pública—. Si la imagen existe pero no se puede cargar (el archivo se
 * borró, el enlace de `storage` no está hecho) cae al mismo marcador: nunca
 * queda el icono de imagen rota con el texto alternativo encima.
 *
 * El tamaño y el recorte los decide quien lo use: las clases que reciba caen
 * sobre el contenedor (`aspect-video`, `size-16`, `rounded-xl`…).
 */
const props = withDefaults(
    defineProps<{
        url: string | null;
        name: string;
        tone?: 'panel' | 'cartel';
    }>(),
    { tone: 'panel' },
);

const failed = ref(false);

watch(
    () => props.url,
    () => (failed.value = false),
);
</script>

<template>
    <div
        class="relative overflow-hidden"
        :class="
            tone === 'cartel'
                ? 'bg-[radial-gradient(90%_120%_at_50%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-carbon)_70%)]'
                : 'bg-muted'
        "
    >
        <img
            v-if="url && !failed"
            :src="url"
            :alt="`Portada de ${name}`"
            loading="lazy"
            class="size-full object-cover"
            @error="failed = true"
        />
        <span v-else class="flex size-full items-center justify-center">
            <Trophy
                class="size-1/3 max-h-10 min-h-4"
                :class="
                    tone === 'cartel'
                        ? 'text-rienda-fuego/40'
                        : 'text-muted-foreground/40'
                "
            />
        </span>
    </div>
</template>
