<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';
import type { Participant } from '@/modules/participants/types';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import type { Option } from '@/modules/shared/types';

const props = defineProps<{
    participant: Participant | null;
    statuses: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vaqueros', href: '/admin/participants' }],
    },
});

const isEditing = props.participant !== null;

const form = useForm({
    first_name: props.participant?.first_name ?? '',
    last_name: props.participant?.last_name ?? '',
    document: props.participant?.document ?? '',
    phone: props.participant?.phone ?? '',
    city: props.participant?.city ?? '',
    country: props.participant?.country ?? 'Colombia',
    status: props.participant?.status ?? 'active',
    photo: null as File | null,
});

function submit(): void {
    if (isEditing) {
        // `useForm` no envía archivos con PUT: se usa el override de método.
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/admin/participants/${props.participant!.public_id}`,
        );

        return;
    }

    form.post('/admin/participants');
}
</script>

<template>
    <Head :title="isEditing ? 'Editar vaquero' : 'Nuevo vaquero'" />

    <div class="p-4">
        <PageHeader
            :eyebrow="participant?.public_id"
            :title="isEditing ? 'Editar vaquero' : 'Nuevo vaquero'"
            description="Los datos de perfil se pueden corregir en cualquier momento sin afectar los resultados oficiales."
        />

        <Card class="max-w-3xl">
            <CardContent class="pt-6">
                <form
                    class="grid gap-5 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="first_name">Nombre</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            required
                        />
                        <InputError :message="form.errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Apellido</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            required
                        />
                        <InputError :message="form.errors.last_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="document">Documento</Label>
                        <Input id="document" v-model="form.document" />
                        <p class="text-xs text-muted-foreground">
                            Es la defensa contra vaqueros duplicados.
                        </p>
                        <InputError :message="form.errors.document" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Teléfono</Label>
                        <Input id="phone" v-model="form.phone" />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="city">Ciudad</Label>
                        <Input id="city" v-model="form.city" />
                        <InputError :message="form.errors.city" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="country">País</Label>
                        <Input id="country" v-model="form.country" />
                        <InputError :message="form.errors.country" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Estado</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="option in statuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Un vaquero inactivo conserva su historial pero no
                            puede inscribirse en nuevos eventos.
                        </p>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="photo">Fotografía</Label>
                        <div class="flex items-start gap-3">
                            <ParticipantAvatar
                                v-if="participant"
                                :name="participant.full_name"
                                :photo-url="participant.photo_url"
                                class="size-12 shrink-0"
                            />
                            <div class="grid flex-1 gap-2">
                                <Input
                                    id="photo"
                                    type="file"
                                    accept="image/*"
                                    @input="
                                        form.photo =
                                            ($event.target as HTMLInputElement)
                                                .files?.[0] ?? null
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    Se usa como avatar en el panel y en la cara
                                    pública. Si no elige una nueva, se conserva
                                    la actual.
                                </p>
                                <InputError :message="form.errors.photo" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-2">
                        <Button type="submit" :disabled="form.processing">
                            {{
                                isEditing
                                    ? 'Guardar cambios'
                                    : 'Registrar vaquero'
                            }}
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link href="/admin/participants">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
