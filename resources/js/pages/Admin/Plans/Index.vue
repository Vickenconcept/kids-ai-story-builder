<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type StoryPlan = {
    id: number;
    name: string;
    description: string | null;
    tier: 'basic' | 'pro' | 'elite';
    included_credits: number;
    price_cents: number;
    currency: string;
    sort_order: number;
    is_active: boolean;
    is_featured: boolean;
    feature_list: string[];
};

const props = defineProps<{
    plans: StoryPlan[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Plans Admin',
        href: '/admin/plans',
    },
];

const createForm = useForm({
    name: '',
    description: '',
    tier: 'basic' as 'basic' | 'pro' | 'elite',
    included_credits: 30,
    price_cents: 0,
    currency: 'USD',
    sort_order: 0,
    is_active: true,
    is_featured: false,
    feature_list: '',
});

const edits = reactive<Record<number, StoryPlan & { featureText: string }>>(
    Object.fromEntries(
        props.plans.map((plan) => [
            plan.id,
            {
                ...plan,
                featureText: (plan.feature_list ?? []).join('\n'),
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
    createForm
        .transform((data) => ({
            ...data,
            feature_list: String(data.feature_list ?? '')
                .split(/\r\n|\r|\n/)
                .map((line) => line.trim())
                .filter(Boolean),
        }))
        .post('/admin/plans', {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset('name', 'description', 'feature_list');
                createForm.tier = 'basic';
                createForm.included_credits = 30;
                createForm.price_cents = 0;
                createForm.currency = 'USD';
                createForm.sort_order = 0;
                createForm.is_active = true;
                createForm.is_featured = false;
            },
        });
};

const savePlan = (planId: number) => {
    const payload = edits[planId];
    if (!payload) {
        return;
    }

    router.patch(`/admin/plans/${planId}`, {
        ...payload,
        feature_list: String(payload.featureText ?? '')
            .split(/\r\n|\r|\n/)
            .map((line) => line.trim())
            .filter(Boolean),
    }, {
        preserveScroll: true,
    });
};

const deletePlan = (planId: number) => {
    if (!confirm('Delete this plan?')) {
        return;
    }

    router.delete(`/admin/plans/${planId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Plans Admin" />

        <div class="min-h-full bg-muted/30 dark:bg-muted/10">
            <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Story Plans</h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">Manage Basic, Pro, and Elite pricing and included credits.</p>
                    </div>
                    <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        Admin
                    </span>
                </div>

                <div class="grid gap-6 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <div class="border-b border-border/60 px-5 py-4">
                                <h2 class="font-semibold">New Plan</h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">Create a plan shown in landing and upgrade pages.</p>
                            </div>
                            <form class="space-y-4 p-5" @submit.prevent="submitCreate">
                                <div class="space-y-1.5">
                                    <Label for="name">Name</Label>
                                    <Input id="name" v-model="createForm.name" placeholder="e.g. Pro" />
                                    <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="tier">Tier</Label>
                                    <select id="tier" v-model="createForm.tier" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                        <option value="basic">Basic</option>
                                        <option value="pro">Pro</option>
                                        <option value="elite">Elite</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1.5">
                                        <Label for="included_credits">Included Credits</Label>
                                        <Input id="included_credits" v-model.number="createForm.included_credits" type="number" min="0" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="price_cents">Price (cents)</Label>
                                        <Input id="price_cents" v-model.number="createForm.price_cents" type="number" min="0" />
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="description">Description</Label>
                                    <Input id="description" v-model="createForm.description" placeholder="Plan description" />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="feature_list">Features (one per line)</Label>
                                    <textarea
                                        id="feature_list"
                                        v-model="createForm.feature_list"
                                        class="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        placeholder="Everything in Basic\nPriority generation"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                        <input v-model="createForm.is_active" type="checkbox" />
                                        Active
                                    </label>
                                    <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                        <input v-model="createForm.is_featured" type="checkbox" />
                                        Featured
                                    </label>
                                </div>

                                <Button type="submit" class="w-full" :disabled="createForm.processing">
                                    {{ createForm.processing ? 'Creating...' : 'Create Plan' }}
                                </Button>
                            </form>
                        </div>
                    </div>

                    <div class="space-y-4 lg:col-span-3">
                        <article
                            v-for="plan in props.plans"
                            :key="plan.id"
                            class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                        >
                            <div class="flex items-center justify-between border-b border-border/60 bg-muted/30 px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ plan.name }}</span>
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold uppercase">{{ plan.tier }}</span>
                                </div>
                                <span class="text-sm font-bold">{{ formatPrice(plan.price_cents, plan.currency) }}</span>
                            </div>

                            <div class="space-y-3 p-5">
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Name</label>
                                        <Input v-model="edits[plan.id].name" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Tier</label>
                                        <select v-model="edits[plan.id].tier" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                            <option value="basic">Basic</option>
                                            <option value="pro">Pro</option>
                                            <option value="elite">Elite</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Included Credits</label>
                                        <Input v-model.number="edits[plan.id].included_credits" type="number" min="0" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Price (cents)</label>
                                        <Input v-model.number="edits[plan.id].price_cents" type="number" min="0" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Currency</label>
                                        <Input v-model="edits[plan.id].currency" maxlength="3" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Sort Order</label>
                                        <Input v-model.number="edits[plan.id].sort_order" type="number" min="0" />
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs text-muted-foreground">Description</label>
                                    <Input :model-value="edits[plan.id].description ?? ''" @update:model-value="(v) => (edits[plan.id].description = String(v))" />
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs text-muted-foreground">Features (one per line)</label>
                                    <textarea v-model="edits[plan.id].featureText" class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                        <input v-model="edits[plan.id].is_active" type="checkbox" />
                                        Active
                                    </label>
                                    <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                        <input v-model="edits[plan.id].is_featured" type="checkbox" />
                                        Featured
                                    </label>
                                </div>

                                <div class="flex items-center justify-between border-t border-border/40 pt-3">
                                    <p class="text-xs text-muted-foreground">
                                        {{ edits[plan.id].included_credits }} credits · {{ formatPrice(edits[plan.id].price_cents, edits[plan.id].currency) }}
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <Button type="button" size="sm" variant="outline" @click="savePlan(plan.id)">Save</Button>
                                        <Button type="button" size="sm" variant="destructive" @click="deletePlan(plan.id)">Delete</Button>
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
