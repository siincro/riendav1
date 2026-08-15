<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Timer, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    AvailableParticipant,
    Registration,
} from '@/modules/registrations/types';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import type { Option } from '@/modules/shared/types';
import type { TournamentCategory } from '@/modules/tournaments/types';

const props = defineProps<{
    category: TournamentCategory;
    registrations: { data: Registration[] };
    statuses: Option[];
    availableParticipants: AvailableParticipant[];
    groups: { id: number; name: string; order: number }[];
}>();

const tournamentSlug = props.category.tournament!.slug;
const basePath = `/admin/tournaments/${tournamentSlug}/categories/${props.category.slug}`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Eventos', href: '/admin/tournaments' }],
    },
});

const form = useForm({
    participant_public_id: '',
    horse_public_id: '',
    registration_number: '' as string | number,
    competition_group_id: '' as string | number,
});

/** Caballos del jinete seleccionado, para no ofrecer los de otro. */
const horsesForSelection = computed(
    () =>
        props.availableParticipants.find(
            (participant) =>
                participant.public_id === form.participant_public_id,
        )?.horses ?? [],
);

function submit(): void {
    form.post(`${basePath}/registrations`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="`Inscripciones · ${category.name}`" />

    <div class="p-4">
        <PageHeader
            :eyebrow="category.tournament?.name"
            :title="`Inscripciones · ${category.name}`"
            description="Una inscripción conecta al vaquero con esta categoría y fija el caballo con el que compite."
        >
            <template #actions>
                <StatusBadge
                    :status="category.status"
                    :label="category.status_label"
                />
                <Button variant="outline" as-child>
                    <Link :href="`/admin/tournaments/${tournamentSlug}`">
                        Volver al evento
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="`${basePath}/competition`">
                        <Timer class="size-4" />
                        Consola
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-1">
                <CardHeader>
                    <CardTitle class="text-base">Inscribir vaquero</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!category.accepts_registrations"
                        class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                    >
                        Esta categoría ya no admite inscripciones: está en
                        progreso, finalizada, o el evento está cerrado.
                    </p>

                    <form v-else class="grid gap-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="participant">Vaquero</Label>
                            <select
                                id="participant"
                                v-model="form.participant_public_id"
                                required
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option value="" disabled>
                                    Seleccione un vaquero
                                </option>
                                <option
                                    v-for="participant in availableParticipants"
                                    :key="participant.public_id"
                                    :value="participant.public_id"
                                >
                                    {{ participant.full_name }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.participant_public_id"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="horse">Caballo</Label>
                            <select
                                id="horse"
                                v-model="form.horse_public_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                :disabled="!form.participant_public_id"
                            >
                                <option value="">Sin caballo asignado</option>
                                <option
                                    v-for="horse in horsesForSelection"
                                    :key="horse.public_id"
                                    :value="horse.public_id"
                                >
                                    {{ horse.name }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.horse_public_id"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="registration_number">Dorsal</Label>
                            <Input
                                id="registration_number"
                                v-model="form.registration_number"
                                type="number"
                                min="1"
                                placeholder="Automático"
                            />
                            <InputError
                                :message="form.errors.registration_number"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="competition_group">Grupo de salida</Label>
                            <select
                                id="competition_group"
                                v-model="form.competition_group_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option value="">Sin grupo</option>
                                <option
                                    v-for="group in groups"
                                    :key="group.id"
                                    :value="group.id"
                                >
                                    {{ group.name }}
                                </option>
                            </select>
                            <p
                                v-if="groups.length === 0"
                                class="text-xs text-muted-foreground"
                            >
                                Cree grupos desde la pantalla del evento para
                                definir el orden de salida.
                            </p>
                            <InputError
                                :message="form.errors.competition_group_id"
                            />
                        </div>

                        <Button type="submit" :disabled="form.processing">
                            <UserPlus class="size-4" />
                            Inscribir
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">
                        Inscritos ({{ registrations.data.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="registrations.data.length === 0"
                        title="Sin inscritos todavía"
                        description="Inscriba vaqueros para poder registrar tiempos en los lazos."
                    />

                    <table v-else class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr class="border-b">
                                <th class="py-2 font-medium">Dorsal</th>
                                <th class="py-2 font-medium">Vaquero</th>
                                <th class="py-2 font-medium">Caballo</th>
                                <th class="py-2 font-medium">Grupo</th>
                                <th class="py-2 text-right font-medium">
                                    Aciertos
                                </th>
                                <th class="py-2 text-right font-medium">
                                    Tiempo
                                </th>
                                <th class="py-2 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="registration in registrations.data"
                                :key="registration.id"
                            >
                                <td class="py-3 font-mono tabular-nums">
                                    {{ registration.registration_number }}
                                </td>
                                <td class="py-3">
                                    <Link
                                        :href="`/admin/participants/${registration.participant.public_id}`"
                                        class="font-medium hover:underline"
                                    >
                                        {{ registration.participant.full_name }}
                                    </Link>
                                </td>
                                <td class="py-3 text-muted-foreground">
                                    {{ registration.horse?.name ?? '—' }}
                                </td>
                                <td class="py-3 text-muted-foreground">
                                    {{ registration.competition_group?.name ?? '—' }}
                                </td>
                                <td class="py-3 text-right tabular-nums">
                                    {{
                                        registration.standing
                                            ?.total_successful_rounds ?? 0
                                    }}
                                </td>
                                <td
                                    class="py-3 text-right font-mono tabular-nums"
                                >
                                    {{
                                        registration.standing
                                            ?.total_time_formatted ?? '0.000'
                                    }}
                                </td>
                                <td class="py-3">
                                    <StatusBadge
                                        :status="registration.status"
                                        :label="registration.status_label"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
