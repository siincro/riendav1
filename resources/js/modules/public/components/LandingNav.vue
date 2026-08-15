<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * La cabecera de la landing.
 *
 * Mezcla dos clases de destino: los anclajes a las secciones de esta misma
 * página y los enlaces a otras páginas de la cara pública. La landing no lista
 * páginas —sus propias secciones ya llevan a torneos y jinetes—, así que quien
 * la usa decide cuáles pasar. En pantallas pequeñas ambos se repliegan a una
 * fila propia.
 */
defineProps<{
    sections: { label: string; href: string }[];
    pages: { label: string; href: string }[];
    /** El botón de la derecha; con `live` marcado late el punto rojo. */
    cta: { label: string; href: string; live: boolean };
}>();

const auth = computed(() => usePage().props.auth);
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b border-rienda-crema/8 bg-rienda-noche/92 backdrop-blur-[10px]"
    >
        <nav
            class="flex items-center justify-between gap-6 px-5 py-4 sm:px-8 lg:px-10"
        >
            <Link href="/" class="flex shrink-0 items-baseline gap-2.5">
                <span
                    class="font-display text-[25px] tracking-[0.03em] text-rienda-crema uppercase"
                >
                    Rienda
                </span>
                <span
                    class="font-condensed text-[15px] tracking-[0.22em] text-rienda-fuego uppercase"
                >
                    .com
                </span>
            </Link>

            <div
                class="hidden items-center gap-7 font-condensed text-base tracking-[0.14em] text-rienda-arena uppercase lg:flex"
            >
                <a
                    v-for="section in sections"
                    :key="section.href"
                    :href="section.href"
                    class="transition-colors hover:text-rienda-crema"
                >
                    {{ section.label }}
                </a>
                <Link
                    v-for="page in pages"
                    :key="page.href"
                    :href="page.href"
                    class="text-rienda-dorado transition-colors hover:text-rienda-crema"
                >
                    {{ page.label }}
                </Link>
            </div>

            <div class="flex shrink-0 items-center gap-4">
                <Link
                    :href="auth.user ? '/dashboard' : '/login'"
                    target="_blank"
                    class="hidden font-condensed text-[15px] tracking-[0.14em] text-rienda-arena uppercase transition-colors hover:text-rienda-crema sm:block"
                >
                    {{ auth.user ? 'Panel' : 'Ingresar' }}
                </Link>

                <component
                    :is="cta.href.startsWith('#') ? 'a' : Link"
                    :href="cta.href"
                    class="flex items-center gap-2.5 rounded-full bg-rienda-fuego px-4.5 py-2.5 font-condensed text-[15px] font-bold tracking-[0.14em] text-rienda-noche uppercase transition-colors hover:bg-rienda-dorado"
                >
                    <span
                        v-if="cta.live"
                        class="size-[7px] animate-pulso rounded-full bg-rienda-noche motion-reduce:animate-none"
                    />
                    {{ cta.label }}
                </component>
            </div>
        </nav>

        <div
            class="flex gap-5 overflow-x-auto border-t border-rienda-crema/8 px-5 py-2.5 font-condensed text-[15px] tracking-[0.14em] whitespace-nowrap text-rienda-arena uppercase sm:px-8 lg:hidden"
        >
            <a
                v-for="section in sections"
                :key="section.href"
                :href="section.href"
            >
                {{ section.label }}
            </a>
            <Link
                v-for="page in pages"
                :key="page.href"
                :href="page.href"
                class="text-rienda-dorado"
            >
                {{ page.label }}
            </Link>
        </div>
    </header>
</template>
