<template>
    <AppLayout :title="`Notification #${log.id}`">
        <div class="max-w-3xl">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ log.subject || log.template_code }}</h1>
                    <div class="mt-1 text-sm text-slate-600 flex items-center gap-3">
                        <span class="badge bg-slate-100 text-slate-700 ring-slate-600/20">{{ log.channel }}</span>
                        <span :class="['badge', statusClass]">{{ log.status }}</span>
                        <span class="text-xs">queued {{ log.queued_at }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Routing</h2>
                    <dl class="mt-3 text-sm space-y-2">
                        <div><dt class="text-slate-500 text-xs">Template</dt><dd class="font-mono">{{ log.template_code }}</dd></div>
                        <div><dt class="text-slate-500 text-xs">Channel</dt><dd>{{ log.channel }}</dd></div>
                        <div><dt class="text-slate-500 text-xs">Recipient</dt><dd>{{ log.recipient }}</dd></div>
                        <div v-if="log.client"><dt class="text-slate-500 text-xs">Client</dt>
                            <dd><Link :href="`/clients/${log.client.id}`" class="text-brand">{{ log.client.code }} — {{ log.client.full_name }}</Link></dd>
                        </div>
                        <div v-if="log.booking_id"><dt class="text-slate-500 text-xs">Booking</dt>
                            <dd><Link :href="`/bookings/${log.booking_id}`" class="text-brand">View booking</Link></dd>
                        </div>
                    </dl>
                </div>

                <div class="card p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Timestamps</h2>
                    <dl class="mt-3 text-sm space-y-2">
                        <div><dt class="text-slate-500 text-xs">Queued</dt><dd>{{ log.queued_at }}</dd></div>
                        <div v-if="log.sent_at"><dt class="text-slate-500 text-xs">Sent</dt><dd>{{ log.sent_at }}</dd></div>
                        <div v-if="log.delivered_at"><dt class="text-slate-500 text-xs">Delivered</dt><dd>{{ log.delivered_at }}</dd></div>
                        <div v-if="log.failed_at"><dt class="text-slate-500 text-xs">Failed</dt><dd class="text-red-700">{{ log.failed_at }}</dd></div>
                        <div v-if="log.provider"><dt class="text-slate-500 text-xs">Provider</dt><dd>{{ log.provider }}</dd></div>
                        <div v-if="log.failure_reason"><dt class="text-slate-500 text-xs">Failure</dt><dd class="text-red-700">{{ log.failure_reason }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="mt-6 card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Subject</h2>
                <p class="mt-2 text-sm">{{ log.subject || '(no subject — SMS or in-app)' }}</p>
                <h2 class="text-sm font-semibold text-slate-900 mt-4">Body</h2>
                <pre class="mt-2 text-sm whitespace-pre-wrap font-sans bg-slate-50 p-3 rounded ring-1 ring-slate-200">{{ log.body }}</pre>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    log: { type: Object, required: true },
});

const statusClass = computed(() => ({
    queued:     'bg-slate-100 text-slate-700 ring-slate-600/20',
    sent:       'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    delivered:  'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    failed:     'bg-red-50 text-red-700 ring-red-600/20',
    suppressed: 'bg-amber-50 text-amber-700 ring-amber-600/20',
})[props.log.status] || 'bg-slate-100 text-slate-700 ring-slate-600/20');
</script>
