<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type Partner = {
    name: string;
    slug: string;
};

type Stats = {
    clicks: number;
    optins: number;
    sales: number;
};

type OptinRow = {
    email: string;
    occurred_at: string | null;
    utm_source: string | null;
    utm_campaign: string | null;
};

type SaleRow = {
    email: string;
    occurred_at: string | null;
    product_id: string | null;
    transaction_id: string | null;
};

const props = defineProps<{
    partner: Partner;
    stats: Stats;
    recent_optins: OptinRow[];
    recent_sales: SaleRow[];
}>();

const conversionRate = computed(() => {
    if (!props.stats.clicks) return '0%';
    return ((props.stats.optins / props.stats.clicks) * 100).toFixed(1) + '%';
});

const saleRate = computed(() => {
    if (!props.stats.optins) return '0%';
    return ((props.stats.sales / props.stats.optins) * 100).toFixed(1) + '%';
});

const fmt = (iso: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`Tracking Stats — ${partner.name}`" />

    <div class="min-h-screen bg-linear-to-br from-violet-950 via-indigo-950 to-purple-950 text-white">
        <!-- Header -->
        <header class="border-b border-white/10 bg-white/5 px-4 py-5">
            <div class="mx-auto flex max-w-5xl items-center gap-3">
                <img src="/images/logo-without-bg.png" alt="DreamForge AI" class="h-8 w-auto object-contain" />
                <div>
                    <p class="text-xs text-violet-400">DreamForge AI · Affiliate Tracking</p>
                    <p class="text-base font-bold">{{ partner.name }}</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-10 px-4 py-10">
            <!-- Stats cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-3xl font-extrabold text-violet-200">{{ stats.clicks.toLocaleString() }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-violet-400">Total Clicks</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-3xl font-extrabold text-fuchsia-300">{{ stats.optins.toLocaleString() }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-violet-400">Opt-ins</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-3xl font-extrabold text-green-300">{{ stats.sales.toLocaleString() }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-violet-400">Sales</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-2xl font-extrabold text-yellow-300">{{ conversionRate }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-violet-400">Click→Optin</p>
                    <p class="mt-0.5 text-[10px] text-violet-500">Optin→Sale: {{ saleRate }}</p>
                </div>
            </div>

            <!-- Recent Opt-ins -->
            <section>
                <h2 class="mb-4 text-base font-bold text-violet-200">Recent Opt-ins (last 30)</h2>
                <div v-if="recent_optins.length === 0" class="rounded-xl border border-white/10 bg-white/5 px-5 py-8 text-center text-sm text-violet-400">
                    No opt-ins recorded yet.
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wider text-violet-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Source</th>
                                <th class="px-4 py-3 text-left">Campaign</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="(row, i) in recent_optins" :key="i" class="transition hover:bg-white/3">
                                <td class="px-4 py-3 text-violet-200">{{ row.email }}</td>
                                <td class="px-4 py-3 text-violet-400">{{ fmt(row.occurred_at) }}</td>
                                <td class="px-4 py-3 text-violet-400">{{ row.utm_source ?? '—' }}</td>
                                <td class="px-4 py-3 text-violet-400">{{ row.utm_campaign ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Recent Sales -->
            <section>
                <h2 class="mb-4 text-base font-bold text-violet-200">Recent Sales (last 30)</h2>
                <div v-if="recent_sales.length === 0" class="rounded-xl border border-white/10 bg-white/5 px-5 py-8 text-center text-sm text-violet-400">
                    No sales attributed yet. Sales are recorded automatically when a buyer's email matches an opt-in from your link.
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wider text-violet-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Product ID</th>
                                <th class="px-4 py-3 text-left">Transaction</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="(row, i) in recent_sales" :key="i" class="transition hover:bg-white/3">
                                <td class="px-4 py-3 text-violet-200">{{ row.email }}</td>
                                <td class="px-4 py-3 text-violet-400">{{ fmt(row.occurred_at) }}</td>
                                <td class="px-4 py-3 text-violet-400">{{ row.product_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-violet-400">{{ row.transaction_id ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="text-center text-xs text-violet-600">Stats refresh with every page load. Data is private to this link — keep it safe.</p>
        </main>
    </div>
</template>
