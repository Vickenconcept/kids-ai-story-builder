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

        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
            <section class="rounded-xl border border-border bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold">Credit Wallet</h1>
                        <p class="text-sm text-muted-foreground">
                            Current balance: <span class="font-semibold text-foreground">{{ storyCredits }} credits</span>
                        </p>
                    </div>
                    <Button type="button" @click="modal.open()">Buy Credits</Button>
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="text-lg font-semibold">Available Packs</h2>
                <p class="mt-1 text-sm text-muted-foreground">One-time payment. No subscription.</p>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <article
                        v-for="pack in packs"
                        :key="pack.id"
                        class="rounded-lg border border-border/80 bg-muted/20 p-4"
                    >
                        <h3 class="font-semibold">{{ pack.name }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ pack.description || 'One-time top-up pack.' }}</p>
                        <p class="mt-3 text-sm">{{ pack.credits }} credits</p>
                        <p class="text-lg font-semibold">{{ formatPrice(pack.price_cents, pack.currency) }}</p>
                        <Button class="mt-3 w-full" type="button" @click="modal.open(pack.id)">
                            Choose Pack
                        </Button>
                    </article>
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="text-lg font-semibold">Recent Purchases</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-160 text-left text-sm">
                        <thead>
                            <tr class="border-b border-border/80 text-muted-foreground">
                                <th class="px-2 py-2 font-medium">Pack</th>
                                <th class="px-2 py-2 font-medium">Credits</th>
                                <th class="px-2 py-2 font-medium">Amount</th>
                                <th class="px-2 py-2 font-medium">Status</th>
                                <th class="px-2 py-2 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="purchase in history"
                                :key="purchase.id"
                                class="border-b border-border/50"
                            >
                                <td class="px-2 py-2">{{ purchase.pack_name }}</td>
                                <td class="px-2 py-2">{{ purchase.credits_awarded }}</td>
                                <td class="px-2 py-2">{{ formatPrice(purchase.amount_cents, purchase.currency) }}</td>
                                <td class="px-2 py-2 capitalize">{{ purchase.status }}</td>
                                <td class="px-2 py-2">{{ purchase.purchased_at ? new Date(purchase.purchased_at).toLocaleString() : '-' }}</td>
                            </tr>
                            <tr v-if="history.length === 0">
                                <td colspan="5" class="px-2 py-4 text-muted-foreground">No purchases yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
