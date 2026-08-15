<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';
import type { Participant } from '@/modules/participants/types';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import Pagination from '@/modules/shared/components/Pagination.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import type { Option, Paginated } from '@/modules/shared/types';

const props = defineProps<{
    participants: Paginated<Participant>;
    filters: { search: string; status: string };
    statuses: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vaqueros', href: '/admin/participants' }],
    },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status);

// Búsqueda con retardo: evita una petición por cada tecla.
let timer: ReturnType<typeof setTimeout>;

watch([search, status], () => {
    clearTimeout(timer);

    timer = setTimeout(() => {
        router.get(
            '/admin/participants',
            { search: search.value, status: status.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Vaqueros" />

    <div class="p-4">
        <PageHeader
            title="Vaqueros"
            description="Los participantes son permanentes: se registran una sola vez y acumulan historial en todos los eventos."
        >
            <template #actions>
                <Button as-child>
                    <Link href="/admin/participants/create">
                        <Plus class="size-4" />
                        Nuevo vaquero
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
                    placeholder="Buscar por nombre, documento o RIENDA ID…"
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
            v-if="participants.data.length === 0"
            title="Todavía no hay vaqueros registrados"
            description="Registre el primer participante para empezar a construir su historial deportivo."
        >
            <Button as-child>
                <Link href="/admin/participants/create">Registrar vaquero</Link>
            </Button>
        </EmptyState>

        <div
            v-else
            class="overflow-hidden rounded-xl border border-zinc-200 bg-white text-zinc-900"
        >
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">RIENDA ID</th>
                        <th class="px-4 py-3 font-medium">Vaquero</th>
                        <th class="px-4 py-3 font-medium">Ciudad</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Caballos
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Eventos
                        </th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <tr
                        v-for="participant in participants.data"
                        :key="participant.public_id"
                        class="transition-colors hover:bg-zinc-50"
                    >
                        <td class="px-4 py-3 font-mono text-xs text-zinc-500">
                            {{ participant.public_id }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <ParticipantAvatar
                                    :name="participant.full_name"
                                    :photo-url="participant.photo_url"
                                    fallback-class="bg-zinc-100 text-zinc-600"
                                    class="size-9"
                                />
                                <div class="min-w-0">
                                    <Link
                                        :href="`/admin/participants/${participant.public_id}`"
                                        class="font-medium hover:underline"
                                    >
                                        {{ participant.full_name }}
                                    </Link>
                                    <p
                                        v-if="participant.document"
                                        class="text-xs text-zinc-500"
                                    >
                                        CC {{ participant.document }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">
                            {{ participant.city ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ participant.horses_count ?? 0 }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ participant.registrations_count ?? 0 }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="participant.status"
                                :label="participant.status_label"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            v-if="participants.data.length > 0"
            :meta="participants.meta"
            :links="participants.links"
        />
    </div>
</template>
