<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { ref, watch } from 'vue';
import LandingSearch from '@/modules/public/components/LandingSearch.vue';
import LandingShell from '@/modules/public/components/LandingShell.vue';
import { tournamentTone } from '@/modules/public/tones';
import Pagination from '@/modules/shared/components/Pagination.vue';
import { formatDateRange, plural } from '@/modules/shared/format';
import type { Paginated } from '@/modules/shared/types';
import TournamentCover from '@/modules/tournaments/components/TournamentCover.vue';
import type { PublicTournament } from '@/modules/tournaments/types';

/**
 * El directorio público de torneos, con el mismo cartel que la landing: la
 * búsqueda vive en la cabecera de cuero y cada torneo se anuncia como una
 * ficha con su estado teñido.
 */
const props = defineProps<{
    tournaments: Paginated<PublicTournament>;
    filters: { search: string };
}>();

const search = ref(props.filters.search);

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);

    timer = setTimeout(() => {
        router.get(
            '/torneos',
            { search: search.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Eventos" />

    <LandingShell :cta="{ label: 'Inicio', href: '/', live: false }">
        <!-- Cabecera y búsqueda -->
        <section
            class="bg-[radial-gradient(120%_90%_at_8%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-noche)_62%)] px-5 py-12 sm:px-8 lg:px-10"
        >
            <p
                class="font-condensed text-[15px] tracking-[0.22em] text-rienda-fuego uppercase"
            >
                Circuito RIENDA
            </p>
            <h1
                class="mt-2.5 font-display text-[clamp(2.75rem,6vw,5rem)] leading-[0.95] text-rienda-crema uppercase"
            >
                Eventos
            </h1>
            <p
                class="mt-3.5 max-w-[46ch] text-base leading-relaxed text-rienda-arena"
            >
                Todos los eventos de lazo con sus fechas, sedes y
                clasificaciones. La clasificación de un evento en pista se
                actualiza con cada manga cronometrada.
            </p>

            <LandingSearch
                v-model="search"
                class="mt-7"
                placeholder="Buscar evento, sede o código…"
            />
        </section>

        <!-- Fichas -->
        <section class="px-5 py-14 sm:px-8 lg:px-10">
            <p
                v-if="tournaments.data.length === 0"
                class="rounded-[20px] border border-dashed border-rienda-crema/15 p-12 text-center text-sm text-rienda-arena"
            >
                Ningún evento coincide con la búsqueda. Aquí aparecen los
                eventos publicados del circuito.
            </p>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="tournament in tournaments.data"
                    :key="tournament.slug"
                    :href="`/torneos/${tournament.slug}`"
                    class="group flex flex-col overflow-hidden rounded-[20px] border border-rienda-crema/8 bg-rienda-tierra transition-colors hover:border-rienda-fuego/55 hover:bg-rienda-cuero"
                >
                    <TournamentCover
                        :url="tournament.cover_image_url"
                        :name="tournament.name"
                        tone="cartel"
                        class="aspect-[16/9] border-b border-rienda-crema/8"
                    />

                    <div class="flex flex-1 flex-col gap-3 p-6">
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="font-condensed text-sm tracking-[0.18em] text-rienda-sombra uppercase"
                            >
                                {{ tournament.public_id }}
                            </span>
                            <span
                                class="rounded-full border px-3 py-1 font-condensed text-sm tracking-[0.14em] whitespace-nowrap uppercase"
                                :class="tournamentTone(tournament)"
                            >
                                {{ tournament.status_label }}
                            </span>
                        </div>

                        <p
                            class="font-display text-[24px] leading-tight text-rienda-crema uppercase"
                        >
                            {{ tournament.name }}
                        </p>

                        <p
                            class="flex items-center gap-1.5 font-condensed text-base tracking-[0.1em] text-rienda-arena uppercase"
                        >
                            <MapPin class="size-4 shrink-0" />
                            {{ tournament.location ?? 'Sede por confirmar' }}
                        </p>

                        <div
                            class="mt-auto flex flex-wrap justify-between gap-2 border-t border-rienda-crema/8 pt-3 font-condensed text-[15px] tracking-[0.08em] text-rienda-sombra uppercase"
                        >
                            <span>
                                {{
                                    formatDateRange(
                                        tournament.start_date,
                                        tournament.end_date,
                                    )
                                }}
                            </span>
                            <span class="text-rienda-fuego">
                                {{
                                    plural(
                                        tournament.categories_count ?? 0,
                                        'categoría',
                                        'categorías',
                                    )
                                }}
                                ·
                                {{
                                    plural(
                                        tournament.registrations_count ?? 0,
                                        'inscrito',
                                        'inscritos',
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <Pagination
                v-if="tournaments.data.length > 0"
                :meta="tournaments.meta"
                :links="tournaments.links"
                tone="cartel"
            />
        </section>
    </LandingShell>
</template>
