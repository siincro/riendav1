<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Paginated, PaginationLink } from '@/modules/shared/types';

const props = withDefaults(
    defineProps<{
        // Solo hacen falta los enlaces y el resumen, no los registros.
        meta: Paginated<unknown>['meta'];
        links: Paginated<unknown>['links'];
        /**
         * Dónde se está pintando: el panel usa los colores neutros del sistema;
         * el cartel, los del tema oscuro de la cara pública.
         */
        tone?: 'panel' | 'cartel';
    }>(),
    { tone: 'panel' },
);

const styles = computed(() =>
    props.tone === 'cartel'
        ? {
              summary:
                  'font-condensed text-sm tracking-[0.14em] uppercase text-rienda-arena',
              disabled:
                  'rounded-full px-3 py-1.5 text-sm text-rienda-sombra/60',
              link: 'rounded-full px-3 py-1.5 font-condensed text-sm tracking-[0.1em] uppercase transition-colors',
              active: 'bg-rienda-fuego text-rienda-noche',
              idle: 'text-rienda-arena hover:text-rienda-crema',
          }
        : {
              summary: 'text-sm text-muted-foreground',
              disabled:
                  'rounded-md px-3 py-1.5 text-sm text-muted-foreground/60',
              link: 'rounded-md px-3 py-1.5 text-sm transition-colors',
              active: 'bg-primary text-primary-foreground',
              idle: 'hover:bg-muted',
          },
);

/**
 * Laravel etiqueta los extremos con entidades HTML («&laquo; Previous»).
 * Se traducen a texto plano para no tener que renderizar HTML crudo.
 */
function label(link: PaginationLink): string {
    const raw = link.label.replace(/&laquo;|&raquo;|«|»/g, '').trim();

    if (/previous|anterior/i.test(raw)) {
        return 'Anterior';
    }

    if (/next|siguiente/i.test(raw)) {
        return 'Siguiente';
    }

    return raw;
}

const items = computed(() =>
    props.links.map((link) => ({ ...link, text: label(link) })),
);
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="flex flex-col items-center justify-between gap-3 pt-4 sm:flex-row"
    >
        <p :class="styles.summary">
            Mostrando {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} de
            {{ meta.total }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            <template
                v-for="(item, index) in items"
                :key="`${item.label}-${index}`"
            >
                <span v-if="!item.url" :class="styles.disabled">
                    {{ item.text }}
                </span>
                <Link
                    v-else
                    :href="item.url"
                    preserve-scroll
                    :class="[
                        styles.link,
                        item.active ? styles.active : styles.idle,
                    ]"
                >
                    {{ item.text }}
                </Link>
            </template>
        </div>
    </nav>
</template>
