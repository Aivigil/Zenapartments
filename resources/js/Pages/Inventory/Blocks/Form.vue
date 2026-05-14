<template>
    <AppLayout :title="isEdit ? 'Edit block' : 'New block'">
        <div class="max-w-2xl">
            <h1 class="text-2xl font-semibold text-slate-900">
                {{ isEdit ? `Edit ${block.name}` : 'New block' }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">Adding to <span class="font-medium">{{ project.name }}</span></p>

            <form @submit.prevent="submit" class="mt-6 card p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Code</label>
                        <input v-model="form.code" type="text" class="input mt-1" required />
                        <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                    </div>
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" type="text" class="input mt-1" required />
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Type</label>
                        <select v-model="form.block_type" class="input mt-1">
                            <option value="block">Block</option>
                            <option value="floor">Floor</option>
                            <option value="sector">Sector</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Sort order</label>
                        <input v-model.number="form.sort_order" type="number" class="input mt-1" />
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link :href="`/inventory/projects/${project.id}`" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create block' }}
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
    project: { type: Object, required: true },
    block: { type: Object, default: null },
});

const isEdit = computed(() => !!props.block?.id);

const form = useForm({
    project_id: props.project.id,
    code: props.block?.code ?? '',
    name: props.block?.name ?? '',
    block_type: props.block?.block_type ?? 'block',
    sort_order: props.block?.sort_order ?? 0,
});

function submit() {
    if (isEdit.value) {
        form.put(`/inventory/blocks/${props.block.id}`);
    } else {
        form.post(`/inventory/projects/${props.project.id}/blocks`);
    }
}
</script>
