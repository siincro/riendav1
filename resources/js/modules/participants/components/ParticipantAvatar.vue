<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

/**
 * La cara de un jinete.
 *
 * Muchos jinetes se registran sin foto, así que las iniciales no son un
 * repuesto de emergencia sino el estado habitual: se pintan con el tono del
 * sitio donde esté —neutro en el panel, de brasa en la cara pública—. El
 * `Avatar` cae solo a las iniciales si la imagen no carga.
 *
 * El tamaño lo decide quien lo use (`size-10`, `size-24`…).
 */
withDefaults(
    defineProps<{
        name: string;
        photoUrl: string | null;
        tone?: 'panel' | 'cartel';
        /** Colores del respaldo cuando el fondo no sigue al tema. */
        fallbackClass?: string;
    }>(),
    { tone: 'panel', fallbackClass: '' },
);
</script>

<template>
    <Avatar>
        <AvatarImage v-if="photoUrl" :src="photoUrl" :alt="name" />
        <AvatarFallback
            :class="
                fallbackClass ||
                (tone === 'cartel'
                    ? 'bg-rienda-fuego/18 font-condensed tracking-[0.06em] text-rienda-dorado'
                    : 'bg-primary/10 text-primary')
            "
        >
            {{ getInitials(name) }}
        </AvatarFallback>
    </Avatar>
</template>
