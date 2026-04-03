<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type CreditPack = {
    id: number;
    name: string;
    description: string | null;
    credits: number;
    price_cents: number;
    currency: string;
    sort_order: number;
    is_active: boolean;
};

const props = defineProps<{
    packs: CreditPack[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Credit Packs',
        href: '/admin/credit-packs',
    },
];

const createForm = useForm({
    name: '',
    description: '',
    credits: 500,
    price_cents: 1000,
    currency: 'USD',
    sort_order: 0,
    is_active: true,
});

const edits = reactive<Record<number, CreditPack>>(
    Object.fromEntries(
        props.packs.map((pack) => [
            pack.id,
            {
                ...pack,
                is_active: Boolean(pack.is_active),
            },
        ]),
    ),
);

const formatPrice = (amountCents: number, currency: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(amountCents / 100);

const submitCreate = () => {
    createForm.post('/admin/credit-packs', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('name', 'description', 'credits', 'price_cents', 'currency', 'sort_order');
            createForm.is_active = true;
            createForm.credits = 500;
            createForm.price_cents = 1000;
            createForm.currency = 'USD';
            createForm.sort_order = 0;
        },
    });
};

const savePack = (packId: number) => {
    const payload = edits[packId];
    if (!payload) {
        return;
    }

    router.patch(`/admin/credit-packs/${packId}`, payload, {
        preserveScroll: true,
    });
};

const deletePack = (packId: number) => {
    if (!confirm('Delete this credit pack?')) {
        return;
    }

    router.delete(`/admin/credit-packs/${packId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Credit Packs" />

        <div class="min-h-full bg-muted/30 dark:bg-muted/10">
            <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">

                <!-- Page header -->
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Credit Packs</h1>
                        <p class="text-muted-foreground mt-0.5 text-sm">Manage one-time top-up packs available to users.</p>
                    </div>
                    <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        Admin
                    </span>
                </div>

                <!-- Two-column layout: form + existing packs -->
                <div class="grid gap-6 lg:grid-cols-5">

                    <!-- Create form (left) -->
                    <div class="lg:col-span-2">
                        <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <div class="border-b border-border/60 px-5 py-4">
                                <h2 class="font-semibold">New Pack</h2>
                                <p class="text-muted-foreground mt-0.5 text-xs">Fill in the fields and click Create.</p>
                            </div>
                            <form class="space-y-4 p-5" @submit.prevent="submitCreate">
                                <div class="space-y-1.5">
                                    <Label for="name" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Name</Label>
                                    <Input id="name" v-model="createForm.name" placeholder="e.g. Starter Pack" />
                                    <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="description" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Description</Label>
                                    <Input id="description" v-model="createForm.description" placeholder="Great for first-time story creation." />
                                    <p v-if="createForm.errors.description" class="text-xs text-destructive">{{ createForm.errors.description }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1.5">
                                        <Label for="credits" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Credits</Label>
                                        <Input id="credits" v-model.number="createForm.credits" type="number" min="1" placeholder="500" />
                                        <p v-if="createForm.errors.credits" class="text-xs text-destructive">{{ createForm.errors.credits }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="price_cents" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Price (cents)</Label>
                                        <Input id="price_cents" v-model.number="createForm.price_cents" type="number" min="1" placeholder="1000" />
                                        <p v-if="createForm.errors.price_cents" class="text-xs text-destructive">{{ createForm.errors.price_cents }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1.5">
                                        <Label for="currency" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Currency</Label>
                                        <Input id="currency" v-model="createForm.currency" maxlength="3" placeholder="USD" />
                                        <p v-if="createForm.errors.currency" class="text-xs text-destructive">{{ createForm.errors.currency }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="sort_order" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sort Order</Label>
                                        <Input id="sort_order" v-model.number="createForm.sort_order" type="number" min="0" placeholder="0" />
                                        <p v-if="createForm.errors.sort_order" class="text-xs text-destructive">{{ createForm.errors.sort_order }}</p>
                                    </div>
                                </div>

                                <!-- Status toggle -->
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</Label>
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-xl border-2 px-4 py-2.5 transition-all"
                                        :class="createForm.is_active
                                            ? 'border-emerald-400 bg-emerald-50/60 dark:border-emerald-600 dark:bg-emerald-950/20'
                                            : 'border-border/60 bg-muted/20'"
                                        @click="createForm.is_active = !createForm.is_active"
                                    >
                                        <span class="text-sm font-medium" :class="createForm.is_active ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'">
                                            {{ createForm.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <span class="relative inline-flex">
                                            <span class="inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                                :class="createForm.is_active ? 'bg-emerald-500' : 'bg-muted'">
                                                <span class="ml-0.5 size-4 rounded-full bg-white shadow transition-transform"
                                                    :class="createForm.is_active ? 'translate-x-4' : ''" />
                                            </span>
                                        </span>
                                    </button>
                                </div>

                                <!-- Live preview -->
                                <div v-if="createForm.name || createForm.credits" class="rounded-xl border border-violet-200/60 bg-violet-50/50 px-4 py-3 text-xs dark:border-violet-800/30 dark:bg-violet-950/20">
                                    <p class="font-semibold text-violet-700 dark:text-violet-300">Preview</p>
                                    <p class="text-muted-foreground mt-0.5">
                                        <span class="font-medium text-foreground">{{ createForm.name || '—' }}</span> ·
                                        {{ createForm.credits }} credits ·
                                        {{ formatPrice(createForm.price_cents, createForm.currency || 'USD') }}
                                    </p>
                                </div>

                                <Button type="submit" class="w-full bg-violet-600 text-white hover:bg-violet-700" :disabled="createForm.processing">
                                    {{ createForm.processing ? 'Creating…' : '+ Create Pack' }}
                                </Button>
                            </form>
                        </div>
                    </div>

                    <!-- Existing packs list (right) -->
                    <div class="lg:col-span-3 space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold">Existing Packs <span class="ml-1.5 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ props.packs.length }}</span></h2>
                        </div>

                        <div v-if="props.packs.length === 0" class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-card/50 py-14 text-center shadow-sm">
                            <div class="flex size-12 items-center justify-center rounded-full bg-muted">
                                <svg class="size-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            </div>
                            <p class="text-sm text-muted-foreground">No packs yet. Create one on the left.</p>
                        </div>

                        <article
                            v-for="pack in props.packs"
                            :key="pack.id"
                            class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border overflow-hidden"
                        >
                            <!-- Pack header -->
                            <div class="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ pack.name }}</span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="pack.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'"
                                    >{{ pack.is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                                <span class="text-sm font-bold text-amber-600 dark:text-amber-400">
                                    {{ formatPrice(pack.price_cents, pack.currency) }}
                                </span>
                            </div>

                            <!-- Pack edit fields -->
                            <div class="p-5 space-y-3">
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Name</label>
                                        <Input v-model="edits[pack.id].name" placeholder="Pack name" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Credits</label>
                                        <Input v-model.number="edits[pack.id].credits" type="number" min="1" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Price (cents)</label>
                                        <Input v-model.number="edits[pack.id].price_cents" type="number" min="1" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Currency</label>
                                        <Input v-model="edits[pack.id].currency" maxlength="3" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Sort Order</label>
                                        <Input v-model.number="edits[pack.id].sort_order" type="number" min="0" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Status</label>
                                        <button
                                            type="button"
                                            class="flex h-10 w-full items-center justify-between rounded-lg border-2 px-3 transition-all"
                                            :class="edits[pack.id].is_active
                                                ? 'border-emerald-400 bg-emerald-50/60 dark:border-emerald-600 dark:bg-emerald-950/20'
                                                : 'border-border/60 bg-muted/20'"
                                            @click="edits[pack.id].is_active = !edits[pack.id].is_active"
                                        >
                                            <span class="text-xs font-semibold" :class="edits[pack.id].is_active ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'">
                                                {{ edits[pack.id].is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            <span class="inline-flex h-4 w-8 items-center rounded-full transition-colors"
                                                :class="edits[pack.id].is_active ? 'bg-emerald-500' : 'bg-muted'">
                                                <span class="ml-0.5 size-3 rounded-full bg-white shadow transition-transform"
                                                    :class="edits[pack.id].is_active ? 'translate-x-4' : ''" />
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Description</label>
                                    <Input
                                        :model-value="edits[pack.id].description ?? ''"
                                        placeholder="Pack description"
                                        @update:model-value="(v) => (edits[pack.id].description = String(v))"
                                    />
                                </div>

                                <!-- Footer row -->
                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border/40 pt-3">
                                    <p class="text-xs text-muted-foreground">
                                        Preview: <span class="font-medium text-foreground">{{ edits[pack.id].credits }} credits</span>
                                        for <span class="font-medium text-foreground">{{ formatPrice(edits[pack.id].price_cents, edits[pack.id].currency) }}</span>
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <Button type="button" size="sm" variant="outline" class="border-violet-300 text-violet-700 hover:bg-violet-50 dark:border-violet-700 dark:text-violet-300" @click="savePack(pack.id)">
                                            Save Changes
                                        </Button>
                                        <Button type="button" size="sm" variant="destructive" @click="deletePack(pack.id)">
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
