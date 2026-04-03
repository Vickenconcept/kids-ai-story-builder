<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useCreditsModal } from '@/composables/useCreditsModal';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type CreditPack = {
    id: number;
    name: string;
    description: string | null;
    credits: number;
    price_cents: number;
    currency: string;
};

type CreditPurchase = {
    id: number;
    pack_name: string;
    credits_awarded: number;
    amount_cents: number;
    currency: string;
    status: string;
    purchased_at: string | null;
};

const props = defineProps<{
    packs: CreditPack[];
    purchases: CreditPurchase[];
    storyCredits: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Credits',
        href: '/credits',
    },
];

const modal = useCreditsModal();

const formatPrice = (amountCents: number, currency: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(amountCents / 100);

const history = computed(() => props.purchases ?? []);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Credits" />

        <div class="min-h-full bg-muted/30 dark:bg-muted/10">
            <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">

                <!-- Balance hero -->
                <section class="relative overflow-hidden rounded-2xl bg-linear-to-br from-violet-600 to-indigo-600 p-6 text-white shadow-lg">
                    <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-violet-200">Current balance</p>
                            <div class="mt-1 flex items-end gap-2">
                                <span class="text-5xl font-bold tracking-tight">{{ storyCredits }}</span>
                                <span class="mb-1.5 text-lg text-violet-200">credits</span>
                            </div>
                            <p class="mt-1 text-sm text-violet-200">Use credits to generate stories, illustrations, narration &amp; video.</p>
                        </div>
                        <Button
                            type="button"
                            class="bg-white text-violet-700 hover:bg-violet-50 font-semibold shadow"
                            @click="modal.open()"
                        >
                            + Buy Credits
                        </Button>
                    </div>
                    <!-- Decorative blobs -->
                    <div class="pointer-events-none absolute -right-8 -top-8 size-40 rounded-full bg-white/10" />
                    <div class="pointer-events-none absolute -bottom-10 -left-6 size-32 rounded-full bg-white/5" />
                </section>

                <!-- Available packs -->
                <section class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-5 dark:border-sidebar-border">
                    <div class="mb-1">
                        <h2 class="text-lg font-semibold">Available Packs</h2>
                        <p class="text-sm text-muted-foreground">One-time payment. No subscription required.</p>
                    </div>

                    <div v-if="packs.length === 0" class="mt-6 py-10 text-center text-sm text-muted-foreground">
                        No credit packs available right now.
                    </div>

                    <div v-else class="mt-4 grid gap-4 md:grid-cols-3">
                        <article
                            v-for="(pack, idx) in packs"
                            :key="pack.id"
                            class="relative flex flex-col rounded-2xl border-2 p-5 transition-all hover:shadow-md"
                            :class="idx === 1
                                ? 'border-violet-400 bg-violet-50/60 dark:border-violet-600 dark:bg-violet-950/20'
                                : 'border-border/60 bg-card hover:border-violet-300'"
                        >
                            <!-- Popular badge on middle pack -->
                            <div
                                v-if="idx === 1"
                                class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-violet-600 px-3 py-0.5 text-xs font-bold text-white shadow"
                            >
                                Most Popular
                            </div>

                            <div class="flex-1">
                                <h3 class="font-bold text-base" :class="idx === 1 ? 'text-violet-800 dark:text-violet-200' : ''">
                                    {{ pack.name }}
                                </h3>
                                <p class="mt-1 text-xs text-muted-foreground">{{ pack.description || 'One-time top-up pack.' }}</p>

                                <div class="mt-4 flex items-end gap-1">
                                    <span class="text-3xl font-bold" :class="idx === 1 ? 'text-violet-700 dark:text-violet-300' : ''">
                                        {{ formatPrice(pack.price_cents, pack.currency) }}
                                    </span>
                                </div>

                                <div class="mt-2 flex items-center gap-1.5">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="idx === 1
                                            ? 'bg-violet-200 text-violet-800 dark:bg-violet-800/40 dark:text-violet-300'
                                            : 'bg-muted text-muted-foreground'">
                                        {{ pack.credits }} credits
                                    </span>
                                </div>
                            </div>

                            <Button
                                class="mt-5 w-full font-semibold"
                                :class="idx === 1 ? 'bg-violet-600 hover:bg-violet-700 text-white' : ''"
                                :variant="idx === 1 ? 'default' : 'outline'"
                                type="button"
                                @click="modal.open(pack.id)"
                            >
                                Choose Pack
                            </Button>
                        </article>
                    </div>
                </section>

                <!-- Purchase history -->
                <section class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-5 dark:border-sidebar-border">
                    <h2 class="text-lg font-semibold">Purchase History</h2>

                    <div v-if="history.length === 0" class="mt-6 flex flex-col items-center gap-2 py-10 text-center">
                        <div class="flex size-12 items-center justify-center rounded-full bg-muted">
                            <svg class="size-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <p class="text-sm text-muted-foreground">No purchases yet.</p>
                        <button type="button" class="text-xs text-violet-600 hover:underline" @click="modal.open()">Buy your first pack →</button>
                    </div>

                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-xl text-left text-sm">
                            <thead>
                                <tr class="border-b border-border/60 bg-muted/30">
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pack</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Credits</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Amount</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="purchase in history"
                                    :key="purchase.id"
                                    class="border-b border-border/40 transition-colors hover:bg-muted/20"
                                >
                                    <td class="px-3 py-3 font-medium">{{ purchase.pack_name }}</td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                            +{{ purchase.credits_awarded }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">{{ formatPrice(purchase.amount_cents, purchase.currency) }}</td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                                            :class="purchase.status === 'completed'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : purchase.status === 'pending'
                                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                                        >
                                            {{ purchase.status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-muted-foreground">
                                        {{ purchase.purchased_at ? new Date(purchase.purchased_at).toLocaleDateString() : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>
