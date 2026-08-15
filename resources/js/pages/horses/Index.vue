<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Horse } from '@/modules/horses/types';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import Pagination from '@/modules/shared/components/Pagination.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import type { Option, Paginated } from '@/modules/shared/types';

const props = defineProps<{
    horses: Paginated<Horse>;
    filters: { search: string; status: string };
    statuses: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Caballos', href: '/admin/horses' }],
    },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status);

let timer: ReturnType<typeof setTimeout>;

watch([search, status], () => {
    clearTimeout(timer);

    timer = setTimeout(() => {
        router.get(
            '/admin/horses',
            { search: search.value, status: status.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Caballos" />

    <div class="p-4">
        <PageHeader
            title="Caballos"
            description="Los caballos son entidades permanentes. La inscripción decide con cuál compite el vaquero en cada evento."
        >
            <template #actions>
                <Button as-child>
                    <Link href="/admin/horses/create">
                        <Plus class="size-4" />
                        Nuevo caballo
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
                    placeholder="Buscar por nombre, ID o propietario…"
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
            v-if="horses.data.length === 0"
            title="Todavía no hay caballos registrados"
            description="Registre los caballos para poder asociarlos a las inscripciones."
        >
            <Button as-child>
                <Link href="/admin/horses/create">Registrar caballo</Link>
            </Button>
        </EmptyState>

        <div
            v-else
            class="overflow-hidden rounded-xl border border-zinc-200 bg-white text-zinc-900"
        >
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">ID</th>
                        <th class="px-4 py-3 font-medium">Caballo</th>
                        <th class="px-4 py-3 font-medium">Vaquero</th>
                        <th class="px-4 py-3 font-medium">Raza</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <tr
                        v-for="horse in horses.data"
                        :key="horse.public_id"
                        class="transition-colors hover:bg-zinc-50"
                    >
                        <td class="px-4 py-3 font-mono text-xs text-zinc-500">
                            {{ horse.public_id }}
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ horse.name }}</p>
                            <p v-if="horse.owner" class="text-xs text-zinc-500">
                                Propietario: {{ horse.owner }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">
                            {{ horse.participant?.full_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-500">
                            {{ horse.breed ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="horse.status"
                                :label="horse.status_label"
                            />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link
                                    :href="`/admin/horses/${horse.public_id}/edit`"
                                >
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            v-if="horses.data.length > 0"
            :meta="horses.meta"
            :links="horses.links"
        />
    </div>
</template>
