<template>
    <AppLayout :title="isEdit ? 'Edit unit' : 'New unit'">
        <div class="max-w-3xl">
            <h1 class="text-2xl font-semibold text-slate-900">
                {{ isEdit ? `Edit ${unit.code}` : 'New unit' }}
            </h1>

            <form @submit.prevent="submit" class="mt-6 card p-6 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">Project</label>
                        <select v-model.number="form.project_id" class="input mt-1" required>
                            <option :value="null">— select —</option>
                            <option v-for="p in lookups.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Block (optional)</label>
                        <select v-model.number="form.block_id" class="input mt-1">
                            <option :value="null">— none —</option>
                            <option v-for="b in blocksFiltered" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Category</label>
                        <select v-model.number="form.unit_category_id" class="input mt-1" required>
                            <option :value="null">— select —</option>
                            <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Code</label>
                        <input v-model="form.code" type="text" class="input mt-1" required />
                        <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                    </div>
                    <div>
                        <label class="label">Name (optional)</label>
                        <input v-model="form.name" type="text" class="input mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">Size value</label>
                        <input v-model.number="form.size_value" type="number" step="0.001" class="input mt-1" />
                    </div>
                    <div>
                        <label class="label">Size unit</label>
                        <select v-model="form.size_unit" class="input mt-1">
                            <option :value="null">—</option>
                            <option v-for="u in lookups.size_units" :key="u" :value="u">{{ u }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select v-model="form.status" class="input mt-1" required>
                            <option v-for="s in lookups.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Base price (major units)</label>
                        <input v-model="form.base_price" type="number" step="0.01" class="input mt-1" required />
                        <p class="mt-1 text-xs text-slate-500">Stored in minor units (paisa) internally.</p>
                    </div>
                    <div>
                        <label class="label">Currency</label>
                        <select v-model="form.currency" class="input mt-1">
                            <option v-for="c in lookups.currencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" class="input mt-1" rows="3"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link href="/inventory/units" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create unit' }}
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
    unit: { type: Object, default: null },
    lookups: { type: Object, required: true },
});

const isEdit = computed(() => !!props.unit?.id);

const form = useForm({
    project_id: props.unit?.project_id ?? null,
    block_id: props.unit?.block_id ?? null,
    unit_category_id: props.unit?.unit_category_id ?? null,
    code: props.unit?.code ?? '',
    name: props.unit?.name ?? '',
    size_value: props.unit?.size_value ?? null,
    size_unit: props.unit?.size_unit ?? null,
    base_price: props.unit?.base_price ?? 0,
    currency: props.unit?.currency ?? 'PKR',
    status: props.unit?.status ?? 'available',
    attributes: props.unit?.attributes ?? {},
    notes: props.unit?.notes ?? '',
});

const blocksFiltered = computed(() =>
    (props.lookups.blocks || []).filter(b => !form.project_id || b.project_id === form.project_id)
);

function submit() {
    if (isEdit.value) {
        form.put(`/inventory/units/${props.unit.id}`);
    } else {
        form.post('/inventory/units');
    }
}
</script>
