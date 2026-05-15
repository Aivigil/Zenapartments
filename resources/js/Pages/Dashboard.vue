<template>
    <AppLayout title="Dashboard">
        <div class="flex items-start justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Welcome back, {{ $page.props.auth.user.name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Operational snapshot for Zen Retreats.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link href="/bookings/create" class="text-sm bg-brand text-white rounded-md px-3 py-1.5 hover:bg-brand-700">+ Booking</Link>
                <Link href="/payments/create" class="text-sm bg-emerald-600 text-white rounded-md px-3 py-1.5 hover:bg-emerald-700">+ Payment</Link>
                <Link href="/clients/create" class="text-sm bg-white text-slate-700 ring-1 ring-slate-300 rounded-md px-3 py-1.5 hover:bg-slate-50">+ Client</Link>
            </div>
        </div>

        <!-- Top row: financial KPIs -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Today's collections</div>
                <div class="mt-1 text-lg lg:text-xl font-semibold text-emerald-700"><Money :minor="totals.pay_today_minor" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Month-to-date</div>
                <div class="mt-1 text-lg lg:text-xl font-semibold text-slate-900"><Money :minor="totals.pay_mtd_minor" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Last 30 days</div>
                <div class="mt-1 text-lg lg:text-xl font-semibold text-slate-900"><Money :minor="totals.pay_30d_minor" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Outstanding (all)</div>
                <div class="mt-1 text-lg lg:text-xl font-semibold text-red-700"><Money :minor="totals.outstanding_minor" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-red-600">Overdue</div>
                <div class="mt-1 text-lg lg:text-xl font-semibold text-red-700">{{ totals.overdue_count }} items</div>
                <div class="text-xs text-slate-500 mt-0.5"><Money :minor="totals.overdue_minor" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Active bookings</div>
                <div class="mt-1 text-lg lg:text-xl font-semibold text-slate-900">{{ totals.bookings_active }}</div>
                <div class="text-xs text-slate-500 mt-0.5">{{ totals.units_available }} avail · {{ totals.units_sold }} sold</div>
            </div>
        </div>

        <!-- Sparkline -->
        <div class="mt-6 card p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-slate-900">Cash-in — last 30 days</h2>
                <Link href="/reports/cash-flow" class="text-xs text-brand">View cash flow →</Link>
            </div>
            <div class="flex items-end gap-1 h-32">
                <div v-for="d in sparkline" :key="d.day" class="flex-1 flex flex-col justify-end group relative">
                    <div :class="['bg-brand/80 rounded-t transition-all hover:bg-brand', d.value > 0 ? '' : 'opacity-30']"
                         :style="{ height: barHeight(d.value) }"></div>
                    <div v-if="d.value > 0" class="absolute -top-7 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] rounded px-1.5 py-0.5 whitespace-nowrap">
                        {{ d.day }}: <Money :minor="d.value" currency="PKR" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 3-column lower row -->
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Recent bookings -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-slate-900">Recent bookings</h2>
                    <Link href="/bookings" class="text-xs text-brand">All →</Link>
                </div>
                <div v-if="recent_bookings.length === 0" class="text-sm text-slate-500">No recent bookings.</div>
                <ul v-else class="space-y-2">
                    <li v-for="b in recent_bookings" :key="b.id" class="text-sm flex items-start gap-2 border-b border-slate-100 pb-2 last:border-0">
                        <Link :href="`/bookings/${b.id}`" class="font-mono text-xs text-brand">{{ b.code }}</Link>
                        <div class="flex-1 min-w-0">
                            <div class="truncate">{{ b.client_name }}</div>
                            <div class="text-xs text-slate-500">{{ b.unit_code }} · {{ b.created_at }}</div>
                        </div>
                        <div class="text-xs text-slate-700"><Money :minor="b.total_minor" currency="PKR" /></div>
                    </li>
                </ul>
            </div>

            <!-- Recent payments -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-slate-900">Recent payments</h2>
                    <Link href="/payments" class="text-xs text-brand">All →</Link>
                </div>
                <div v-if="recent_payments.length === 0" class="text-sm text-slate-500">No recent payments.</div>
                <ul v-else class="space-y-2">
                    <li v-for="p in recent_payments" :key="p.id" class="text-sm flex items-start gap-2 border-b border-slate-100 pb-2 last:border-0">
                        <Link :href="`/payments/${p.id}`" class="font-mono text-xs text-brand">{{ p.code }}</Link>
                        <div class="flex-1 min-w-0">
                            <div class="truncate">{{ p.client_name }}</div>
                            <div class="text-xs text-slate-500">{{ p.booking_code }} · {{ p.received_at }} · {{ p.channel }}</div>
                        </div>
                        <div class="text-xs text-emerald-700 font-medium"><Money :minor="p.amount_minor" currency="PKR" /></div>
                    </li>
                </ul>
            </div>

            <!-- Activity feed -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-slate-900">Activity</h2>
                    <Link href="/admin/audit" class="text-xs text-brand">All →</Link>
                </div>
                <div v-if="activity.length === 0" class="text-sm text-slate-500">No recent activity.</div>
                <ul v-else class="space-y-2">
                    <li v-for="e in activity" :key="e.id" class="text-xs border-b border-slate-100 pb-2 last:border-0">
                        <div class="flex items-center gap-2">
                            <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded">{{ e.event }}</span>
                            <span class="text-slate-500 text-[10px]">{{ e.occurred_at }}</span>
                        </div>
                        <div class="mt-1 text-slate-700">
                            {{ e.actor || 'system' }}
                            <span v-if="e.subject_type" class="text-slate-500">· {{ e.subject_type }} #{{ e.subject_id }}</span>
                        </div>
                        <div v-if="e.reason" class="text-slate-500 italic mt-0.5">{{ e.reason }}</div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    totals: { type: Object, required: true },
    sparkline: { type: Array, required: true },
    recent_bookings: { type: Array, required: true },
    recent_payments: { type: Array, required: true },
    activity: { type: Array, required: true },
});

const maxSpark = computed(() => Math.max(1, ...props.sparkline.map(d => d.value)));

function barHeight(v) {
    return Math.max(2, Math.round((v / maxSpark.value) * 100)) + '%';
}
</script>
