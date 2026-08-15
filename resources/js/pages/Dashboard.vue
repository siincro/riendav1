<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Rabbit, Timer, Trophy, Users } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import { dashboard } from '@/routes';

type LiveCategory = {
    name: string;
    slug: string;
    status: string;
    status_label: string;
    registrations_count: number;
    tournament: { name: string; slug: string };
};

type RecentTournament = {
    public_id: string;
    name: string;
    slug: string;
    status: string;
    status_label: string;
    categories_count: number;
};

const props = defineProps<{
    stats: {
        participants: number;
        horses: number;
        tournaments: number;
        results: number;
    };
    liveCategories: LiveCategory[];
    recentTournaments: RecentTournament[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

const tiles = [
    { label: 'Vaqueros activos', value: props.stats.participants, icon: Users },
    { label: 'Caballos activos', value: props.stats.horses, icon: Rabbit },
    { label: 'Eventos', value: props.stats.tournaments, icon: Trophy },
    {
        label: 'Resultados registrados',
        value: props.stats.results,
        icon: Timer,
    },
];
</script>

<template>
    <Head title="Panel" />

    <div class="p-4">
        <PageHeader
            title="RIENDA"
            description="La plataforma oficial de los vaqueros de lazo."
        />

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="tile in tiles" :key="tile.label">
                <CardContent class="flex items-center gap-4 pt-6">
                    <div class="rounded-lg bg-muted p-2.5">
                        <component
                            :is="tile.icon"
                            class="size-5 text-muted-foreground"
                        />
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ tile.value }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ tile.label }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">En competencia</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="liveCategories.length === 0"
                        title="No hay eventos en progreso"
                        description="Cuando un evento pase a «En progreso», sus categorías aparecerán aquí."
                    />

                    <ul v-else class="divide-y">
                        <li
                            v-for="category in liveCategories"
                            :key="`${category.tournament.slug}-${category.slug}`"
                            class="py-3"
                        >
                            <Link
                                :href="`/admin/tournaments/${category.tournament.slug}/categories/${category.slug}/competition`"
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ category.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ category.tournament.name }} ·
                                        {{ category.registrations_count }}
                                        inscrito(s)
                                    </p>
                                </div>
                                <StatusBadge
                                    :status="category.status"
                                    :label="category.status_label"
                                />
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Eventos recientes</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="recentTournaments.length === 0"
                        title="Todavía no hay eventos"
                        description="Cree el primer evento para empezar."
                    />

                    <ul v-else class="divide-y">
                        <li
                            v-for="tournament in recentTournaments"
                            :key="tournament.slug"
                            class="py-3"
                        >
                            <Link
                                :href="`/admin/tournaments/${tournament.slug}`"
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ tournament.name }}
                                    </p>
                                    <p
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ tournament.public_id }} ·
                                        {{ tournament.categories_count }}
                                        categoría(s)
                                    </p>
                                </div>
                                <StatusBadge
                                    :status="tournament.status"
                                    :label="tournament.status_label"
                                />
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
