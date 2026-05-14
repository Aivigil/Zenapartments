<template>
    <AppLayout :title="isEdit ? 'Edit project' : 'New project'">
        <div class="max-w-3xl">
            <h1 class="text-2xl font-semibold text-slate-900">
                {{ isEdit ? `Edit ${project.name}` : 'New project' }}
            </h1>

            <form @submit.prevent="submit" class="mt-6 card p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Code</label>
                        <input v-model="form.code" type="text" class="input mt-1" placeholder="e.g. ZR-BAR" required />
                        <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                    </div>
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" type="text" class="input mt-1" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                </div>

                <div>
                    <label class="label">Location</label>
                    <input v-model="form.location" type="text" class="input mt-1" placeholder="Barian, Murree" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">City</label>
                        <input v-model="form.city" type="text" class="input mt-1" />
                    </div>
                    <div>
                        <label class="label">Country</label>
                        <input v-model="form.country" type="text" maxlength="2" class="input mt-1" placeholder="PK" />
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select v-model="form.status" class="input mt-1">
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link href="/inventory/projects" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create project' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    project: { type: Object, default: null },
});

const isEdit = computed(() => !!props.project?.id);

const form = useForm({
    code: props.project?.code ?? '',
    name: props.project?.name ?? '',
    location: props.project?.location ?? '',
    city: props.project?.city ?? '',
    country: props.project?.country ?? 'PK',
    status: props.project?.status ?? 'active',
    metadata: props.project?.metadata ?? null,
});

function submit() {
    if (isEdit.value) {
        form.put(`/inventory/projects/${props.project.id}`);
    } else {
        form.post('/inventory/projects');
    }
}
</script>
