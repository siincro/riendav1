<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, MapPin, Users } from '@lucide/vue';
import { computed } from 'vue';
import LandingBreadcrumb from '@/modules/public/components/LandingBreadcrumb.vue';
import LandingHeading from '@/modules/public/components/LandingHeading.vue';
import LandingShell from '@/modules/public/components/LandingShell.vue';
import { categoryTone, tournamentTone } from '@/modules/public/tones';
import { dayCount, formatDateRange, plural } from '@/modules/shared/format';
import TournamentCover from '@/modules/tournaments/components/TournamentCover.vue';
import type {
    PublicTournament,
    PublicTournamentCategory,
} from '@/modules/tournaments/types';

/**
 * La ficha pública de un torneo: el cartel del evento y sus categorías como
 * puertas a cada clasificación.
 */
const props = defineProps<{
    tournament: PublicTournament;
}>();

const categories = computed(() => props.tournament.categories ?? []);

/**
 * Los inscritos del torneo se suman de sus categorías: el recurso público solo
 * trae el conteo cuando la consulta lo pidió, y aquí la unidad que sí lo tiene
 * siempre es la categoría.
 */
const registrations = computed(
    () =>
        props.tournament.registrations_count ??
        categories.value.reduce(
            (total, category) => total + (category.registrations_count ?? 0),
            0,
        ),
);

const metrics = computed(() => [
    {
        value: String(
            props.tournament.categories_count ?? categories.value.length,
        ),
        label: 'Categorías',
    },
    {
        value: String(registrations.value),
        label: 'Inscritos',
    },
    {
        value: dayCount(props.tournament.start_date, props.tournament.end_date),
        label: 'Días de feria',
    },
]);

function categoryUrl(category: PublicTournamentCategory): string {
    return `/torneos/${props.tournament.slug}/categorias/${category.slug}`;
}
</script>

<template>
    <Head :title="tournament.name" />

    <LandingShell :cta="{ label: 'Inicio', href: '/', live: false }">
        <!-- Ficha del evento -->
        <section
            class="bg-[radial-gradient(120%_90%_at_8%_0%,var(--color-rienda-cuero)_0%,var(--color-rienda-noche)_62%)] px-5 py-12 sm:px-8 lg:px-10"
        >
            <LandingBreadcrumb
                :items="[
                    { label: 'Eventos', href: '/torneos' },
                    { label: tournament.name },
                ]"
                class="mb-7"
            />

            <div class="flex flex-wrap items-center gap-3">
                <span
                    class="font-condensed text-sm tracking-[0.18em] text-rienda-sombra uppercase"
                >
                    {{ tournament.public_id }}
                </span>
                <span
                    class="rounded-full border px-3 py-1 font-condensed text-sm tracking-[0.14em] uppercase"
                    :class="tournamentTone(tournament)"
                >
                    {{ tournament.status_label }}
                </span>
            </div>

            <h1
                class="mt-3 font-display text-[clamp(2.5rem,5.6vw,4.75rem)] leading-[0.95] text-balance text-rienda-crema uppercase"
            >
                {{ tournament.name }}
            </h1>

            <div
                class="mt-3.5 flex flex-wrap items-center gap-x-6 gap-y-2 font-condensed text-base tracking-[0.1em] text-rienda-arena uppercase"
            >
                <span class="flex items-center gap-1.5">
                    <MapPin class="size-4 shrink-0" />
                    {{ tournament.location ?? 'Sede por confirmar' }}
                </span>
                <span class="flex items-center gap-1.5">
                    <CalendarDays class="size-4 shrink-0" />
                    {{
                        formatDateRange(
                            tournament.start_date,
                            tournament.end_date,
                        )
                    }}
                </span>
                <span class="flex items-center gap-1.5">
                    <Users class="size-4 shrink-0" />
                    {{ plural(registrations, 'inscrito', 'inscritos') }}
                </span>
            </div>

            <p
                v-if="tournament.description"
                class="mt-5 max-w-[60ch] text-base leading-relaxed text-rienda-arena"
            >
                {{ tournament.description }}
            </p>

            <TournamentCover
                :url="tournament.cover_image_url"
                :name="tournament.name"
                tone="cartel"
                class="mt-8 aspect-[21/9] rounded-[22px] border border-rienda-crema/12"
            />

            <div class="mt-4 grid grid-cols-3 gap-3.5 sm:max-w-2xl">
                <div
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="rounded-2xl border border-rienda-crema/8 bg-rienda-tierra px-4.5 py-4"
                >
                    <p class="font-display text-[28px] text-rienda-crema">
                        {{ metric.value }}
                    </p>
                    <p
                        class="font-condensed text-sm tracking-[0.14em] text-rienda-arena uppercase"
                    >
                        {{ metric.label }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Categorías -->
        <section class="bg-rienda-carbon px-5 py-14 sm:px-8 lg:px-10">
            <LandingHeading kicker="En competencia" title="Categorías" />

            <p
                v-if="categories.length === 0"
                class="mt-7 rounded-[20px] border border-dashed border-rienda-crema/15 p-12 text-center text-sm text-rienda-arena"
            >
                Este evento todavía no tiene categorías publicadas.
            </p>

            <div v-else class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="categoryUrl(category)"
                    class="group flex flex-col gap-3 rounded-[20px] border border-rienda-crema/8 bg-rienda-tierra p-6 transition-colors hover:border-rienda-fuego/55 hover:bg-rienda-cuero"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span
                            class="rounded-full border px-3 py-1 font-condensed text-sm tracking-[0.14em] uppercase"
                            :class="categoryTone(category.status)"
                        >
                            {{ category.status_label }}
                        </span>
                        <span
                            class="flex items-center gap-1.5 font-condensed text-sm tracking-[0.14em] text-rienda-sombra uppercase"
                        >
                            <Users class="size-3.5" />
                            {{ category.registrations_count ?? 0 }}
                        </span>
                    </div>

                    <p
                        class="font-display text-[24px] leading-tight text-rienda-crema uppercase"
                    >
                        {{ category.name }}
                    </p>

                    <p
                        v-if="category.description"
                        class="line-clamp-2 text-sm leading-relaxed text-rienda-arena"
                    >
                        {{ category.description }}
                    </p>

                    <div
                        class="mt-auto flex items-center justify-between gap-3 border-t border-rienda-crema/8 pt-3 font-condensed text-[15px] tracking-[0.08em] text-rienda-sombra uppercase"
                    >
                        <span>
                            {{ plural(category.number_of_days, 'día', 'días') }}
                            ·
                            {{ plural(category.total_rounds, 'lazo', 'lazos') }}
                        </span>
                        <ArrowRight
                            class="size-4 text-rienda-fuego transition-transform group-hover:translate-x-0.5"
                        />
                    </div>
                </Link>
            </div>
        </section>
    </LandingShell>
</template>
