<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/**
 * Las migas del cartel. El último tramo es el sitio donde se está, así que va
 * sin enlace: cualquier elemento sin `href` se pinta como texto.
 */
defineProps<{
    items: { label: string; href?: string }[];
}>();
</script>

<template>
    <nav
        class="flex flex-wrap items-center gap-2.5 font-condensed text-sm tracking-[0.16em] text-rienda-sombra uppercase"
    >
        <template v-for="(item, index) in items" :key="item.label">
            <span v-if="index > 0">/</span>
            <Link
                v-if="item.href"
                :href="item.href"
                class="transition-colors hover:text-rienda-dorado"
            >
                {{ item.label }}
            </Link>
            <span v-else class="truncate text-rienda-arena">{{
                item.label
            }}</span>
        </template>
    </nav>
</template>
