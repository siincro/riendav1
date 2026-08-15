<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';
import type {
    Participant,
    ParticipantHorse,
} from '@/modules/participants/types';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';

const props = defineProps<{
    participant: Participant;
    horses: ParticipantHorse[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vaqueros', href: '/admin/participants' }],
    },
});

const details = [
    { label: 'Documento', value: props.participant.document },
    { label: 'Teléfono', value: props.participant.phone },
    { label: 'Ciudad', value: props.participant.city },
    { label: 'País', value: props.participant.country },
];
</script>

<template>
    <Head :title="participant.full_name" />

    <div class="p-4">
        <div class="mb-2 flex items-center gap-4">
            <ParticipantAvatar
                :name="participant.full_name"
                :photo-url="participant.photo_url"
                class="size-14"
            />

            <PageHeader
                class="flex-1"
                :eyebrow="participant.public_id"
                :title="participant.full_name"
                description="Historial deportivo permanente en RIENDA."
            >
                <template #actions>
                    <StatusBadge
                        :status="participant.status"
                        :label="participant.status_label"
                    />
                    <Button variant="outline" as-child>
                        <Link
                            :href="`/admin/participants/${participant.public_id}/edit`"
                        >
                            <Pencil class="size-4" />
                            Editar
                        </Link>
                    </Button>
                </template>
            </PageHeader>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">Datos básicos</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div v-for="detail in details" :key="detail.label">
                            <dt class="text-xs text-muted-foreground">
                                {{ detail.label }}
                            </dt>
                            <dd class="font-medium">
                                {{ detail.value ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Trayectoria</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ participant.registrations_count ?? 0 }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Inscripciones
                        </p>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ participant.horses_count ?? 0 }}
                        </p>
                        <p class="text-xs text-muted-foreground">Caballos</p>
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-3">
                <CardHeader>
                    <CardTitle class="text-base">Caballos</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="horses.length === 0"
                        title="Sin caballos asociados"
                        description="Registre un caballo y asócielo a este vaquero para poder inscribirlo con él."
                    >
                        <Button variant="outline" as-child>
                            <Link href="/admin/horses/create"
                                >Registrar caballo</Link
                            >
                        </Button>
                    </EmptyState>

                    <ul v-else class="divide-y">
                        <li
                            v-for="horse in horses"
                            :key="horse.public_id"
                            class="flex items-center justify-between py-3"
                        >
                            <div>
                                <p class="font-medium">{{ horse.name }}</p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ horse.public_id }}
                                </p>
                            </div>
                            <StatusBadge
                                :status="horse.status"
                                :label="
                                    horse.status === 'active'
                                        ? 'Activo'
                                        : 'Inactivo'
                                "
                            />
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
