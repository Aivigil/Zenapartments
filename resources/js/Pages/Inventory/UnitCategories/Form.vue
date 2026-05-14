<template>
    <AppLayout :title="isEdit ? 'Edit category' : 'New category'">
        <div class="max-w-xl">
            <h1 class="text-2xl font-semibold text-slate-900">
                {{ isEdit ? `Edit ${category.name}` : 'New category' }}
            </h1>

            <form @submit.prevent="submit" class="mt-6 card p-6 space-y-4">
                <div>
                    <label class="label">Code</label>
                    <input v-model="form.code" type="text" class="input mt-1" required />
                </div>
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" type="text" class="input mt-1" required />
                </div>
                <div>
                    <label class="label">Kind</label>
                    <select v-model="form.kind" class="input mt-1" required>
                        <option value="plot">Plot</option>
                        <option value="chalet">Chalet</option>
                        <option value="apartment">Apartment</option>
                        <option value="studio">Studio</option>
                    </select>
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea v-model="form.description" class="input mt-1" rows="3"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link href="/inventory/unit-categories" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create category' }}
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

const props = defineProps({ category: { type: Object, default: null } });
const isEdit = computed(() => !!props.category?.id);

const form = useForm({
    code: props.category?.code ?? '',
    name: props.category?.name ?? '',
    kind: props.category?.kind ?? 'apartment',
    description: props.category?.description ?? '',
});

function submit() {
    if (isEdit.value) {
        form.put(`/inventory/unit-categories/${props.category.id}`);
    } else {
        form.post('/inventory/unit-categories');
    }
}
</script>
