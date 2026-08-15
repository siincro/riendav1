<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/** El pie de la landing: la marca, la promesa y los tres destinos públicos. */
const links = [
    { label: 'Eventos', href: '/torneos' },
    { label: 'Vaqueros', href: '/jinetes' },
    { label: 'Ingresar', href: '/login' },
];
</script>

<template>
    <footer
        class="flex flex-wrap items-center justify-between gap-6 border-t border-rienda-crema/8 bg-rienda-carbon px-5 py-8 sm:px-8 lg:px-10"
    >
        <div class="flex items-baseline gap-2.5">
            <span class="font-display text-[22px] text-rienda-crema uppercase">
                Rienda
            </span>
            <span
                class="font-condensed text-[14px] tracking-[0.22em] text-rienda-fuego uppercase"
            >
                .com
            </span>
        </div>

        <p
            class="font-condensed text-[15px] tracking-[0.14em] text-rienda-sombra uppercase"
        >
            Eventos de vaquería y lazo · Colombia
        </p>

        <div
            class="flex gap-5 font-condensed text-[15px] tracking-[0.14em] uppercase"
        >
            <Link
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="text-rienda-dorado transition-colors hover:text-rienda-crema"
            >
                {{ link.label }}
            </Link>
        </div>
    </footer>
</template>
