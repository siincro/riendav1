<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import TournamentCover from '@/modules/tournaments/components/TournamentCover.vue';
import type { Tournament } from '@/modules/tournaments/types';

const props = defineProps<{
    tournament: Tournament | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Eventos', href: '/admin/tournaments' }],
    },
});

const isEditing = props.tournament !== null;

const form = useForm({
    name: props.tournament?.name ?? '',
    slug: props.tournament?.slug ?? '',
    description: props.tournament?.description ?? '',
    location: props.tournament?.location ?? '',
    start_date: props.tournament?.start_date ?? '',
    end_date: props.tournament?.end_date ?? '',
    cover_image: null as File | null,
});

function submit(): void {
    if (isEditing) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/admin/tournaments/${props.tournament!.slug}`,
        );

        return;
    }

    form.post('/admin/tournaments');
}
</script>

<template>
    <Head :title="isEditing ? 'Editar evento' : 'Nuevo evento'" />

    <div class="p-4">
        <PageHeader
            :eyebrow="tournament?.public_id"
            :title="isEditing ? 'Editar evento' : 'Nuevo evento'"
            description="El evento nace como borrador: no es visible al público hasta que se publica."
        />

        <Card class="max-w-3xl">
            <CardContent class="pt-6">
                <form
                    class="grid gap-5 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="name">Nombre</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="slug">Identificador en la URL</Label>
                        <Input
                            id="slug"
                            v-model="form.slug"
                            placeholder="Se genera a partir del nombre"
                        />
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="description">Descripción</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="location">Lugar</Label>
                        <Input id="location" v-model="form.location" />
                        <InputError :message="form.errors.location" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="start_date">Fecha de inicio</Label>
                        <Input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                        />
                        <p class="text-xs text-muted-foreground">
                            Las fechas de los días de competencia se derivan de
                            esta.
                        </p>
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="end_date">Fecha de fin</Label>
                        <Input
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="cover_image">Imagen de portada</Label>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start"
                        >
                            <TournamentCover
                                v-if="tournament"
                                :url="tournament.cover_image_url"
                                :name="tournament.name"
                                class="aspect-video w-full shrink-0 rounded-lg border sm:w-40"
                            />
                            <div class="grid flex-1 gap-2">
                                <Input
                                    id="cover_image"
                                    type="file"
                                    accept="image/*"
                                    @input="
                                        form.cover_image =
                                            ($event.target as HTMLInputElement)
                                                .files?.[0] ?? null
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    Se muestra en el directorio público y en la
                                    ficha del evento. Si no elige una nueva, se
                                    conserva la actual.
                                </p>
                                <InputError
                                    :message="form.errors.cover_image"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ isEditing ? 'Guardar cambios' : 'Crear evento' }}
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link href="/admin/tournaments">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
