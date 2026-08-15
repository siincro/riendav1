<script setup lang="ts">
import LandingFooter from '@/modules/public/components/LandingFooter.vue';
import LandingNav from '@/modules/public/components/LandingNav.vue';
import LandingTicker from '@/modules/public/components/LandingTicker.vue';

/**
 * El armazón del cartel: fondo de cuero, cabecera y pie.
 *
 * Lo comparten las páginas públicas que se pintan con el tema oscuro de
 * RIENDA. La cinta y los anclajes son de quien los pase —la landing los llena
 * con lo que está pasando en pista, las páginas interiores los dejan vacíos—,
 * así que la cabecera se ve igual en todas sin que ninguna repita su markup.
 */
withDefaults(
    defineProps<{
        ticker?: string[];
        sections?: { label: string; href: string }[];
        /** Los otros destinos públicos; la landing los deja en blanco. */
        pages?: { label: string; href: string }[];
        cta?: { label: string; href: string; live: boolean };
    }>(),
    {
        ticker: () => [],
        sections: () => [],
        pages: () => [
            { label: 'Eventos', href: '/torneos' },
            { label: 'Vaqueros', href: '/jinetes' },
        ],
        cta: () => ({ label: 'Ver eventos', href: '/torneos', live: false }),
    },
);
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-rienda-noche font-landing text-rienda-crema"
    >
        <LandingTicker :items="ticker" />
        <LandingNav :sections="sections" :pages="pages" :cta="cta" />

        <main class="flex-1">
            <slot />
        </main>

        <LandingFooter />
    </div>
</template>
