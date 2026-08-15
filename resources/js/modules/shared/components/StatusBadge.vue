<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

/**
 * Traduce el estado de cualquier entidad de RIENDA a un color consistente en
 * todo el panel: verde para lo que está vivo, ámbar para lo que espera,
 * gris para lo cerrado y rojo para lo cancelado.
 */
const props = defineProps<{
    status: string;
    label: string;
}>();

const tone = computed(() => {
    switch (props.status) {
        case 'active':
        case 'in_progress':
        case 'published':
            return 'border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-400';
        case 'pending':
        case 'draft':
            return 'border-transparent bg-amber-500/15 text-amber-700 dark:text-amber-400';
        case 'finished':
        case 'inactive':
            return 'border-transparent bg-muted text-muted-foreground';
        case 'cancelled':
        case 'disqualified':
            return 'border-transparent bg-red-500/15 text-red-700 dark:text-red-400';
        case 'withdrawn':
            return 'border-transparent bg-orange-500/15 text-orange-700 dark:text-orange-400';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
});

const isLive = computed(
    () => props.status === 'in_progress' || props.status === 'active',
);
</script>

<template>
    <Badge :class="tone">
        <span
            v-if="isLive"
            class="mr-1.5 inline-block size-1.5 rounded-full bg-current"
        />
        {{ label }}
    </Badge>
</template>
