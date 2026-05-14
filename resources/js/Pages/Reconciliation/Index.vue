<template>
    <AppLayout title="Reconciliation">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Bank reconciliation</h1>
                <p class="mt-1 text-sm text-slate-500">Import bank-statement CSVs, then confirm suggested matches to post payments.</p>
            </div>
        </div>

        <!-- Upload form -->
        <div class="mt-6 card p-6">
            <h2 class="text-base font-semibold text-slate-900">Import a bank statement</h2>
            <p class="text-xs text-slate-500 mt-1">CSV only, max 10 MB. Default column names match most Pakistani-bank exports — override if yours differ.</p>

            <form @submit.prevent="upload" enctype="multipart/form-data" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="label">Bank account label</label>
                    <input v-model="form.bank_account" type="text" class="input mt-1" placeholder="e.g. HBL Zen Operating 12345" required />
                </div>
                <div>
                    <label class="label">CSV file</label>
                    <input @change="form.file = $event.target.files[0]" type="file" accept=".csv,text/csv" class="input mt-1" required />
                </div>

                <details class="md:col-span-3">
                    <summary class="text-sm text-brand cursor-pointer">Advanced — custom column names</summary>
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <label>Date column<input v-model="form.col_date" type="text" class="input mt-1" placeholder="Transaction Date" /></label>
                        <label>Description column<input v-model="form.col_description" type="text" class="input mt-1" placeholder="Description" /></label>
                        <label>Credit column<input v-model="form.col_credit" type="text" class="input mt-1" placeholder="Credit" /></label>
                        <label>Debit column<input v-model="form.col_debit" type="text" class="input mt-1" placeholder="Debit" /></label>
                        <label>Single amount column (signed)<input v-model="form.col_amount" type="text" class="input mt-1" placeholder="Amount" /></label>
                        <label>Reference column<input v-model="form.col_reference" type="text" class="input mt-1" placeholder="Reference" /></label>
                        <label>Counterparty column<input v-model="form.col_counterparty" type="text" class="input mt-1" placeholder="Counterparty" /></label>
                    </div>
                </details>

                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Upload + auto-match</button>
                </div>
            </form>
        </div>

        <!-- Past imports -->
        <h2 class="mt-8 text-lg font-semibold text-slate-900">Past imports</h2>
        <div class="mt-3 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Uploaded</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Bank account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Filename</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Lines</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Pending</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Confirmed</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="i in imports.data" :key="i.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ i.created_at }}</td>
                        <td class="px-4 py-3 text-sm">{{ i.bank_account }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ i.period_start }} → {{ i.period_end }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600 truncate max-w-[200px]" :title="i.source_filename">{{ i.source_filename }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ i.total_lines }}</td>
                        <td class="px-4 py-3 text-sm text-right text-amber-700">{{ i.pending_count }}</td>
                        <td class="px-4 py-3 text-sm text-right text-emerald-700">{{ i.confirmed_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/reconciliation/${i.id}`" class="text-sm text-brand hover:text-brand-dark">Review</Link>
                        </td>
                    </tr>
                    <tr v-if="imports.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No imports yet. Upload a bank statement CSV above to start.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    imports: { type: Object, required: true },
});

const form = useForm({
    file: null,
    bank_account: '',
    col_date: '',
    col_description: '',
    col_credit: '',
    col_debit: '',
    col_amount: '',
    col_reference: '',
    col_counterparty: '',
});

function upload() {
    form.post('/reconciliation/upload', { forceFormData: true });
}
</script>
