<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Swords, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import ChallengeCard from '@/modules/challenges/components/ChallengeCard.vue';
import type { Challenge, ChallengeableEvent } from '@/modules/challenges/types';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';

/**
 * Los retos desde el panel.
 *
 * Un reto enfrenta a dos vaqueros de un mismo evento, corran la categoría que
 * corran: a cada uno se le mide con lo que lleva en la suya, gana quien más
 * lazos acierte y el menor tiempo desempata. Mientras está en juego el ganador
 * se recalcula solo con cada lazo, y al resolverlo queda congelado.
 */
const props = defineProps<{
    challenges: { data: Challenge[] };
    events: ChallengeableEvent[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Retos', href: '/admin/challenges' }],
    },
});

const showForm = ref(false);

const form = useForm({
    tournament_slug: '',
    challenger_public_id: '',
    rival_public_id: '',
});

/** Solo se retan vaqueros inscritos en el evento elegido. */
const contenders = computed(
    () =>
        props.events.find((event) => event.slug === form.tournament_slug)
            ?.contenders ?? [],
);

/** El rival no puede ser el mismo vaquero que retó. */
const rivals = computed(() =>
    contenders.value.filter(
        (contender) => contender.public_id !== form.challenger_public_id,
    ),
);

function resetContenders(): void {
    form.challenger_public_id = '';
    form.rival_public_id = '';
}

function submit(): void {
    form.post('/admin/challenges', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

function settle(challenge: Challenge): void {
    router.put(
        `/admin/challenges/${challenge.public_id}/settle`,
        {},
        { preserveScroll: true },
    );
}

function cancel(challenge: Challenge): void {
    router.put(
        `/admin/challenges/${challenge.public_id}/cancel`,
        {},
        { preserveScroll: true },
    );
}

function destroy(challenge: Challenge): void {
    router.delete(`/admin/challenges/${challenge.public_id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Retos" />

    <div class="p-4">
        <PageHeader
            title="Retos"
            description="Dos vaqueros de un mismo evento, cara a cara: gana quien más lazos acierte y el menor tiempo desempata."
        >
            <template #actions>
                <Button size="sm" @click="showForm = !showForm">
                    <Plus class="size-4" />
                    Nuevo reto
                </Button>
            </template>
        </PageHeader>

        <Card v-if="showForm" class="mb-6">
            <CardHeader>
                <CardTitle class="text-base">Armar un reto</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-4 sm:grid-cols-3"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="tournament">Evento</Label>
                        <select
                            id="tournament"
                            v-model="form.tournament_slug"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            required
                            @change="resetContenders"
                        >
                            <option value="" disabled>Elija un evento</option>
                            <option
                                v-for="event in events"
                                :key="event.slug"
                                :value="event.slug"
                            >
                                {{ event.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.tournament_slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="challenger">Retador</Label>
                        <select
                            id="challenger"
                            v-model="form.challenger_public_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            required
                            :disabled="contenders.length === 0"
                        >
                            <option value="" disabled>Elija un vaquero</option>
                            <option
                                v-for="contender in contenders"
                                :key="contender.public_id"
                                :value="contender.public_id"
                            >
                                {{ contender.full_name }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.challenger_public_id"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="rival">Rival</Label>
                        <select
                            id="rival"
                            v-model="form.rival_public_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            required
                            :disabled="contenders.length === 0"
                        >
                            <option value="" disabled>Elija un vaquero</option>
                            <option
                                v-for="contender in rivals"
                                :key="contender.public_id"
                                :value="contender.public_id"
                            >
                                {{ contender.full_name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.rival_public_id" />
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-3">
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="form.processing"
                        >
                            Crear reto
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="showForm = false"
                        >
                            Cancelar
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            Solo aparecen los vaqueros inscritos en el evento;
                            da igual la categoría de cada uno.
                        </p>
                    </div>
                </form>
            </CardContent>
        </Card>

        <EmptyState
            v-if="challenges.data.length === 0"
            title="Todavía no hay retos"
            description="Elija el evento y a los dos vaqueros; a cada uno se le mide en la categoría que corre allí."
        >
            <Button @click="showForm = true">
                <Swords class="size-4" />
                Armar el primero
            </Button>
        </EmptyState>

        <div v-else class="grid gap-4 lg:grid-cols-2">
            <ChallengeCard
                v-for="challenge in challenges.data"
                :key="challenge.public_id"
                :challenge="challenge"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t pt-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ challenge.tournament.name }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <template v-if="challenge.status === 'open'">
                            <Button
                                size="sm"
                                :disabled="challenge.is_tied"
                                @click="settle(challenge)"
                            >
                                Resolver
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="cancel(challenge)"
                            >
                                Cancelar
                            </Button>
                        </template>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(challenge)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </ChallengeCard>
        </div>
    </div>
</template>
