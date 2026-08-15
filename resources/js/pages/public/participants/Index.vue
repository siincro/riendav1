<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';
import type { PublicParticipant } from '@/modules/participants/types';
import LandingSearch from '@/modules/public/components/LandingSearch.vue';
import LandingShell from '@/modules/public/components/LandingShell.vue';
import Pagination from '@/modules/shared/components/Pagination.vue';
import { plural } from '@/modules/shared/format';
import type { Paginated } from '@/modules/shared/types';

/**
 * El directorio público de jinetes, con el cartel de la landing: cada uno se
 * anuncia con sus iniciales, su ciudad y cuántos torneos lleva corridos.
 */
const props = defineProps<{
    participants: Paginated<PublicParticipant>;
    filters: { search: string };
}>();

const search = ref(props.filters.search);

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);

    timer = setTimeout(() => {
        router.get(
            '/jinetes',
            { search: search.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

function origin(rider: PublicParticipant): string {
    return [rider.city, rider.country].filter(Boolean).join(', ') || 'Colombia';
}
</script>

<template>
    <Head title="Vaqueros" />

    <LandingShell :cta="{ label: 'Inicio', href: '/', live: false }">
        <!-- Cabecera y búsqueda -->
        <section
            class="bg-[radial-gradient(120%_90%_at_8%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-noche)_62%)] px-5 py-12 sm:px-8 lg:px-10"
        >
            <p
                class="font-condensed text-[15px] tracking-[0.22em] text-rienda-fuego uppercase"
            >
                Directorio
            </p>
            <h1
                class="mt-2.5 font-display text-[clamp(2.75rem,6vw,5rem)] leading-[0.95] text-rienda-crema uppercase"
            >
                Vaqueros
            </h1>
            <p
                class="mt-3.5 max-w-[46ch] text-base leading-relaxed text-rienda-arena"
            >
                Todos los vaqueros de lazo registrados en RIENDA. Cada ficha
                guarda su cuadra y el historial de las categorías que ha
                corrido.
            </p>

            <LandingSearch
                v-model="search"
                class="mt-7"
                placeholder="Buscar vaquero…"
            />
        </section>

        <!-- Fichas -->
        <section class="px-5 py-14 sm:px-8 lg:px-10">
            <p
                v-if="participants.data.length === 0"
                class="rounded-[20px] border border-dashed border-rienda-crema/15 p-12 text-center text-sm text-rienda-arena"
            >
                Ningún vaquero coincide con la búsqueda. Pruebe con otro nombre.
            </p>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="rider in participants.data"
                    :key="rider.public_id"
                    :href="`/jinetes/${rider.public_id}`"
                    class="flex items-center gap-4 rounded-[20px] border border-rienda-crema/8 bg-rienda-tierra p-5 transition-colors hover:border-rienda-fuego/55 hover:bg-rienda-cuero"
                >
                    <ParticipantAvatar
                        :name="rider.full_name"
                        :photo-url="rider.photo_url"
                        tone="cartel"
                        class="size-12 border border-rienda-crema/12 text-[17px] font-bold"
                    />

                    <div class="min-w-0">
                        <p
                            class="truncate font-display text-[21px] leading-tight text-rienda-crema uppercase"
                        >
                            {{ rider.full_name }}
                        </p>
                        <p
                            class="truncate font-condensed text-base tracking-[0.1em] text-rienda-arena uppercase"
                        >
                            {{ origin(rider) }}
                        </p>
                    </div>

                    <span
                        class="ml-auto shrink-0 text-right font-condensed text-[15px] tracking-[0.08em] text-rienda-fuego uppercase"
                    >
                        {{
                            plural(
                                rider.registrations_count ?? 0,
                                'evento',
                                'eventos',
                            )
                        }}
                    </span>
                </Link>
            </div>

            <Pagination
                v-if="participants.data.length > 0"
                :meta="participants.meta"
                :links="participants.links"
                tone="cartel"
            />
        </section>
    </LandingShell>
</template>
