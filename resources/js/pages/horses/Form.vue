<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Horse, HorseOwnerOption } from '@/modules/horses/types';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import type { Option } from '@/modules/shared/types';

const props = defineProps<{
    horse: Horse | null;
    statuses: Option[];
    sexes: Option[];
    participants: HorseOwnerOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Caballos', href: '/admin/horses' }],
    },
});

const isEditing = props.horse !== null;

const form = useForm({
    name: props.horse?.name ?? '',
    status: props.horse?.status ?? 'active',
    participant_public_id: props.horse?.participant?.public_id ?? '',
    owner: props.horse?.owner ?? '',
    breed: props.horse?.breed ?? '',
    color: props.horse?.color ?? '',
    sex: props.horse?.sex ?? '',
    birth_date: props.horse?.birth_date ?? '',
    photo: null as File | null,
});

function submit(): void {
    if (isEditing) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/admin/horses/${props.horse!.public_id}`,
        );

        return;
    }

    form.post('/admin/horses');
}
</script>

<template>
    <Head :title="isEditing ? 'Editar caballo' : 'Nuevo caballo'" />

    <div class="p-4">
        <PageHeader
            :eyebrow="horse?.public_id"
            :title="isEditing ? 'Editar caballo' : 'Nuevo caballo'"
            description="Solo el nombre y el estado son obligatorios; el resto de la ficha puede completarse después."
        />

        <Card class="max-w-3xl">
            <CardContent class="pt-6">
                <form
                    class="grid gap-5 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nombre</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
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
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="participant">Vaquero</Label>
                        <select
                            id="participant"
                            v-model="form.participant_public_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option value="">Sin vaquero asociado</option>
                            <option
                                v-for="participant in participants"
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
                        <Label for="owner">Propietario</Label>
                        <Input id="owner" v-model="form.owner" />
                        <InputError :message="form.errors.owner" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="breed">Raza</Label>
                        <Input id="breed" v-model="form.breed" />
                        <InputError :message="form.errors.breed" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="color">Color</Label>
                        <Input id="color" v-model="form.color" />
                        <InputError :message="form.errors.color" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="sex">Sexo</Label>
                        <select
                            id="sex"
                            v-model="form.sex"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option value="">Sin especificar</option>
                            <option
                                v-for="option in sexes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.sex" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="birth_date">Fecha de nacimiento</Label>
                        <Input
                            id="birth_date"
                            v-model="form.birth_date"
                            type="date"
                        />
                        <InputError :message="form.errors.birth_date" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="photo">Fotografía</Label>
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
                        <InputError :message="form.errors.photo" />
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-2">
                        <Button type="submit" :disabled="form.processing">
                            {{
                                isEditing
                                    ? 'Guardar cambios'
                                    : 'Registrar caballo'
                            }}
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link href="/admin/horses">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
