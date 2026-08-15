<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MapPin, Plus, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import Pagination from '@/modules/shared/components/Pagination.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import { formatDateRange } from '@/modules/shared/format';
import type { Option, Paginated } from '@/modules/shared/types';
import TournamentCover from '@/modules/tournaments/components/TournamentCover.vue';
import type { Tournament } from '@/modules/tournaments/types';

const props = defineProps<{
    tournaments: Paginated<Tournament>;
    filters: { search: string; status: string };
    statuses: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Eventos', href: '/admin/tournaments' }],
    },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status);

let timer: ReturnType<typeof setTimeout>;

watch([search, status], () => {
    clearTimeout(timer);

    timer = setTimeout(() => {
        router.get(
            '/admin/tournaments',
            { search: search.value, status: status.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Eventos" />

    <div class="p-4">
        <PageHeader
            title="Eventos"
            description="Cada evento agrupa categorías; cada categoría define su propia estructura de días y lazos."
        >
            <template #actions>
                <Button as-child>
                    <Link href="/admin/tournaments/create">
                        <Plus class="size-4" />
                        Nuevo evento
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Buscar evento…"
                />
            </div>

            <select
                v-model="status"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
            >
                <option value="">Todos los estados</option>
                <option
                    v-for="option in statuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <EmptyState
            v-if="tournaments.data.length === 0"
            title="Todavía no hay eventos"
            description="Cree un evento, configure sus categorías y publíquelo para que sea visible."
        >
            <Button as-child>
                <Link href="/admin/tournaments/create">Crear evento</Link>
            </Button>
        </EmptyState>

        <div v-else class="grid gap-3">
            <Link
                v-for="tournament in tournaments.data"
                :key="tournament.slug"
                :href="`/admin/tournaments/${tournament.slug}`"
                class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-zinc-900 transition-colors hover:bg-zinc-50 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <TournamentCover
                        :url="tournament.cover_image_url"
                        :name="tournament.name"
                        class="hidden aspect-video w-28 shrink-0 rounded-lg border border-zinc-200 sm:block"
                    />

                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs text-zinc-500">
                                {{ tournament.public_id }}
                            </span>
                            <StatusBadge
                                :status="tournament.status"
                                :label="tournament.status_label"
                            />
                        </div>
                        <p class="text-lg font-semibold">
                            {{ tournament.name }}
                        </p>
                        <p
                            v-if="tournament.location"
                            class="flex items-center gap-1 text-sm text-zinc-500"
                        >
                            <MapPin class="size-3.5" />
                            {{ tournament.location }}
                        </p>
                    </div>
                </div>

                <div class="text-left sm:text-right">
                    <p class="text-sm">
                        {{
                            formatDateRange(
                                tournament.start_date,
                                tournament.end_date,
                            )
                        }}
                    </p>
                    <p class="text-xs text-zinc-500">
                        {{ tournament.categories_count ?? 0 }} categoría(s)
                    </p>
                </div>
            </Link>
        </div>

        <Pagination
            v-if="tournaments.data.length > 0"
            :meta="tournaments.meta"
            :links="tournaments.links"
        />
    </div>
</template>
