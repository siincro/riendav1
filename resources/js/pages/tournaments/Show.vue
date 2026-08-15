<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    MapPin,
    Pencil,
    Plus,
    Timer,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/modules/shared/components/EmptyState.vue';
import PageHeader from '@/modules/shared/components/PageHeader.vue';
import StatusBadge from '@/modules/shared/components/StatusBadge.vue';
import { formatDateRange } from '@/modules/shared/format';
import TournamentCover from '@/modules/tournaments/components/TournamentCover.vue';
import type {
    CompetitionGroup,
    Tournament,
    TournamentCategory,
} from '@/modules/tournaments/types';

const props = defineProps<{
    tournament: Tournament;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Eventos', href: '/admin/tournaments' }],
    },
});

const showCategoryForm = ref(false);

/** La categoría que se está editando; `null` mientras se crea una nueva. */
const editing = ref<TournamentCategory | null>(null);

const categoryForm = useForm({
    name: '',
    // El slug viaja tal cual al editar: renombrar una categoría no debe
    // cambiar la URL pública por la que ya la enlazan.
    slug: '',
    description: '',
    order: 0,
    number_of_days: 1,
    rounds_per_day: 4,
});

const showGroupForm = ref(false);

/** El grupo que se está editando; `null` mientras se crea uno nuevo. */
const editingGroup = ref<CompetitionGroup | null>(null);

const groupForm = useForm({
    name: '',
    order: 0,
});

/**
 * Días y rondas solo se pueden tocar mientras la categoría no haya arrancado:
 * el backend rechaza el cambio y aquí se bloquea antes de intentarlo.
 */
const structureLocked = computed(
    () => editing.value !== null && !editing.value.structure_is_editable,
);

function openCreateForm(): void {
    editing.value = null;
    categoryForm.clearErrors();

    Object.assign(categoryForm, {
        name: '',
        slug: '',
        description: '',
        order: props.tournament.categories?.length ?? 0,
        number_of_days: 1,
        rounds_per_day: 4,
    });

    showCategoryForm.value = true;
}

function openEditForm(category: TournamentCategory): void {
    editing.value = category;
    categoryForm.clearErrors();

    Object.assign(categoryForm, {
        name: category.name,
        slug: category.slug,
        description: category.description ?? '',
        order: category.order,
        number_of_days: category.number_of_days,
        rounds_per_day: category.rounds_per_day,
    });

    showCategoryForm.value = true;
}

function closeCategoryForm(): void {
    showCategoryForm.value = false;
    editing.value = null;
    categoryForm.clearErrors();
}

function toggleCategoryForm(): void {
    if (showCategoryForm.value && editing.value === null) {
        closeCategoryForm();

        return;
    }

    openCreateForm();
}

function submitCategory(): void {
    const categories = `/admin/tournaments/${props.tournament.slug}/categories`;

    const options = {
        preserveScroll: true,
        onSuccess: (): void => closeCategoryForm(),
    };

    if (editing.value === null) {
        categoryForm.post(categories, options);

        return;
    }

    categoryForm.put(`${categories}/${editing.value.slug}`, options);
}

function openGroupCreateForm(): void {
    editingGroup.value = null;
    groupForm.clearErrors();

    Object.assign(groupForm, {
        name: '',
        order: props.tournament.competition_groups?.length ?? 0,
    });

    showGroupForm.value = true;
}

function openGroupEditForm(group: CompetitionGroup): void {
    editingGroup.value = group;
    groupForm.clearErrors();

    Object.assign(groupForm, {
        name: group.name,
        order: group.order,
    });

    showGroupForm.value = true;
}

function closeGroupForm(): void {
    showGroupForm.value = false;
    editingGroup.value = null;
    groupForm.clearErrors();
}

function toggleGroupForm(): void {
    if (showGroupForm.value && editingGroup.value === null) {
        closeGroupForm();

        return;
    }

    openGroupCreateForm();
}

function submitGroup(): void {
    const groups = `/admin/tournaments/${props.tournament.slug}/groups`;

    const options = {
        preserveScroll: true,
        onSuccess: (): void => closeGroupForm(),
    };

    if (editingGroup.value === null) {
        groupForm.post(groups, options);

        return;
    }

    groupForm.put(`${groups}/${editingGroup.value.id}`, options);
}

function destroyGroup(group: CompetitionGroup): void {
    router.delete(
        `/admin/tournaments/${props.tournament.slug}/groups/${group.id}`,
        { preserveScroll: true },
    );
}

const confirmingDelete = ref(false);

function destroyTournament(): void {
    router.delete(`/admin/tournaments/${props.tournament.slug}`, {
        onFinish: () => (confirmingDelete.value = false),
    });
}

function changeStatus(status: string): void {
    router.put(
        `/admin/tournaments/${props.tournament.slug}/status`,
        { status },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="tournament.name" />

    <div class="p-4">
        <PageHeader :eyebrow="tournament.public_id" :title="tournament.name">
            <template #actions>
                <StatusBadge
                    :status="tournament.status"
                    :label="tournament.status_label"
                />

                <Button
                    v-for="transition in tournament.allowed_transitions"
                    :key="transition.value"
                    :variant="
                        transition.value === 'cancelled' ? 'ghost' : 'default'
                    "
                    size="sm"
                    @click="changeStatus(transition.value)"
                >
                    {{ transition.label }}
                </Button>

                <Button size="sm" as-child>
                    <Link :href="`/admin/tournaments/${tournament.slug}/competition`">
                        <Timer class="size-4" />
                        Consola
                    </Link>
                </Button>

                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/admin/tournaments/${tournament.slug}/edit`">
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                </Button>

                <!--
                    Borrar solo está disponible mientras el evento esté vacío:
                    con inscritos dentro, el camino es cancelarlo.
                -->
                <Button
                    v-if="tournament.can_be_deleted"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="confirmingDelete = true"
                >
                    <Trash2 class="size-4" />
                    Eliminar
                </Button>
            </template>
        </PageHeader>

        <div
            class="mb-6 flex flex-col gap-5 rounded-xl border border-zinc-200 bg-white p-4 text-zinc-900 sm:flex-row sm:items-start"
        >
            <TournamentCover
                :url="tournament.cover_image_url"
                :name="tournament.name"
                class="aspect-video w-full shrink-0 rounded-xl border border-zinc-200 sm:w-64"
            />

            <div class="min-w-0 space-y-3">
                <div
                    class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-500"
                >
                    <span class="flex items-center gap-1.5">
                        <CalendarDays class="size-4" />
                        {{
                            formatDateRange(
                                tournament.start_date,
                                tournament.end_date,
                            )
                        }}
                    </span>
                    <span
                        v-if="tournament.location"
                        class="flex items-center gap-1.5"
                    >
                        <MapPin class="size-4" />
                        {{ tournament.location }}
                    </span>
                </div>

                <p v-if="tournament.description" class="max-w-2xl text-sm">
                    {{ tournament.description }}
                </p>
            </div>
        </div>

        <Card class="mb-6 border-zinc-200 bg-white text-zinc-900">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0"
            >
                <div>
                    <CardTitle class="text-base">Grupos de salida</CardTitle>
                    <p class="text-xs text-zinc-500">
                        Definen el orden en el que los vaqueros salen a pista
                        desde la consola del evento.
                    </p>
                </div>
                <Button size="sm" variant="outline" @click="toggleGroupForm">
                    <Plus class="size-4" />
                    Agregar grupo
                </Button>
            </CardHeader>

            <CardContent class="space-y-4">
                <form
                    v-if="showGroupForm"
                    class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 sm:grid-cols-4"
                    @submit.prevent="submitGroup"
                >
                    <p class="text-sm font-medium sm:col-span-4">
                        {{
                            editingGroup
                                ? `Editar ${editingGroup.name}`
                                : 'Nuevo grupo de salida'
                        }}
                    </p>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="group_name">Nombre</Label>
                        <Input
                            id="group_name"
                            v-model="groupForm.name"
                            placeholder="Apertura"
                            required
                        />
                        <InputError :message="groupForm.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="group_order">Orden</Label>
                        <Input
                            id="group_order"
                            v-model="groupForm.order"
                            type="number"
                            min="0"
                            max="999"
                            required
                        />
                        <InputError :message="groupForm.errors.order" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3 sm:col-span-4">
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="groupForm.processing"
                        >
                            {{ editingGroup ? 'Guardar cambios' : 'Crear grupo' }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="closeGroupForm"
                        >
                            Cancelar
                        </Button>
                    </div>
                </form>

                <EmptyState
                    v-if="
                        !tournament.competition_groups ||
                        tournament.competition_groups.length === 0
                    "
                    title="Sin grupos de salida"
                    description="Los grupos permiten que la consola del evento muestre el orden exacto de salida sin cambiar de categoría."
                />

                <ul v-else class="divide-y divide-zinc-200">
                    <li
                        v-for="group in tournament.competition_groups"
                        :key="group.id"
                        class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <p class="font-medium">{{ group.name }}</p>
                            <p class="text-sm text-zinc-500">
                                Orden {{ group.order }} ·
                                {{ group.registrations_count ?? 0 }} inscrito(s)
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openGroupEditForm(group)"
                            >
                                <Pencil class="size-4" />
                                Editar
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="destroyGroup(group)"
                            >
                                <Trash2 class="size-4" />
                                Eliminar
                            </Button>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card class="border-zinc-200 bg-white text-zinc-900">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0"
            >
                <CardTitle class="text-base">Categorías</CardTitle>
                <Button size="sm" variant="outline" @click="toggleCategoryForm">
                    <Plus class="size-4" />
                    Agregar categoría
                </Button>
            </CardHeader>

            <CardContent class="space-y-4">
                <form
                    v-if="showCategoryForm"
                    class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 sm:grid-cols-4"
                    @submit.prevent="submitCategory"
                >
                    <p class="text-sm font-medium sm:col-span-4">
                        {{
                            editing
                                ? `Editar ${editing.name}`
                                : 'Nueva categoría'
                        }}
                    </p>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="category_name">Nombre</Label>
                        <Input
                            id="category_name"
                            v-model="categoryForm.name"
                            placeholder="Profesional"
                            required
                        />
                        <InputError
                            :message="
                                categoryForm.errors.name ??
                                categoryForm.errors.slug
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="number_of_days">Días</Label>
                        <Input
                            id="number_of_days"
                            v-model="categoryForm.number_of_days"
                            type="number"
                            min="1"
                            max="30"
                            required
                            :disabled="structureLocked"
                        />
                        <InputError
                            :message="categoryForm.errors.number_of_days"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="rounds_per_day">Lazos por día</Label>
                        <Input
                            id="rounds_per_day"
                            v-model="categoryForm.rounds_per_day"
                            type="number"
                            min="1"
                            max="20"
                            required
                            :disabled="structureLocked"
                        />
                        <InputError
                            :message="categoryForm.errors.rounds_per_day"
                        />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="category_description">Descripción</Label>
                        <textarea
                            id="category_description"
                            v-model="categoryForm.description"
                            rows="2"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            placeholder="Opcional: se muestra en la ficha pública de la categoría."
                        />
                        <InputError
                            :message="categoryForm.errors.description"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="category_order">Orden</Label>
                        <Input
                            id="category_order"
                            v-model="categoryForm.order"
                            type="number"
                            min="0"
                            max="999"
                        />
                        <InputError :message="categoryForm.errors.order" />
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-3 sm:col-span-4"
                    >
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="categoryForm.processing"
                        >
                            {{
                                editing ? 'Guardar cambios' : 'Crear categoría'
                            }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="closeCategoryForm"
                        >
                            Cancelar
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="structureLocked">
                                La categoría ya arrancó: su estructura de días y
                                lazos queda fija para no invalidar los tiempos
                                registrados.
                            </template>
                            <template v-else>
                                {{
                                    editing ? 'Se regenerarán' : 'Se generarán'
                                }}
                                {{
                                    categoryForm.number_of_days *
                                    categoryForm.rounds_per_day +
                                    1
                                }}
                                lazos: las rondas regulares de cada día más el
                                lazo final, que solo corre el top 10 de la
                                clasificación.
                            </template>
                        </p>
                    </div>
                </form>

                <EmptyState
                    v-if="
                        !tournament.categories ||
                        tournament.categories.length === 0
                    "
                    title="Sin categorías configuradas"
                    description="Un evento no puede publicarse hasta que tenga al menos una categoría."
                />

                <ul v-else class="divide-y divide-zinc-200">
                    <li
                        v-for="category in tournament.categories"
                        :key="category.slug"
                        class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium">{{ category.name }}</p>
                                <StatusBadge
                                    :status="category.status"
                                    :label="category.status_label"
                                />
                            </div>
                            <p class="text-sm text-zinc-500">
                                {{ category.number_of_days }} día(s) ×
                                {{ category.rounds_per_day }} lazos +
                                final =
                                {{ category.total_rounds }} lazos ·
                                {{ category.registrations_count ?? 0 }}
                                inscrito(s)
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEditForm(category)"
                            >
                                <Pencil class="size-4" />
                                Editar
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="`/admin/tournaments/${tournament.slug}/categories/${category.slug}/registrations`"
                                >
                                    <Users class="size-4" />
                                    Inscripciones
                                </Link>
                            </Button>
                            <Button size="sm" as-child>
                                <Link
                                    :href="`/admin/tournaments/${tournament.slug}/categories/${category.slug}/competition`"
                                >
                                    <Timer class="size-4" />
                                    Consola
                                </Link>
                            </Button>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Dialog v-model:open="confirmingDelete">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>¿Eliminar {{ tournament.name }}?</DialogTitle>
                    <DialogDescription>
                        Se borran también sus categorías con sus días y lazos.
                        El evento no tiene vaqueros inscritos, así que no se
                        pierde ningún resultado. Esta acción no se puede
                        deshacer.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="destroyTournament">
                        <Trash2 class="size-4" />
                        Eliminar evento
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
