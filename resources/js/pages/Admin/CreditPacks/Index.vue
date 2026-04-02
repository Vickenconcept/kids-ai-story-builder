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

        <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
            <section class="rounded-xl border border-border bg-card p-5">
                <h1 class="text-xl font-semibold">Create Credit Pack</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Configure one-time packs users can purchase inside the app.
                </p>

                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="submitCreate">
                    <div class="space-y-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="createForm.name" placeholder="Starter Pack" />
                        <p v-if="createForm.errors.name" class="text-sm text-destructive">{{ createForm.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Input id="description" v-model="createForm.description" placeholder="Great for first-time story creation." />
                        <p v-if="createForm.errors.description" class="text-sm text-destructive">{{ createForm.errors.description }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="credits">Credits</Label>
                        <Input id="credits" v-model.number="createForm.credits" type="number" min="1" placeholder="500" />
                        <p v-if="createForm.errors.credits" class="text-sm text-destructive">{{ createForm.errors.credits }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="price_cents">Price (cents)</Label>
                        <Input id="price_cents" v-model.number="createForm.price_cents" type="number" min="1" placeholder="1000" />
                        <p v-if="createForm.errors.price_cents" class="text-sm text-destructive">{{ createForm.errors.price_cents }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="currency">Currency</Label>
                        <Input id="currency" v-model="createForm.currency" maxlength="3" placeholder="USD" />
                        <p v-if="createForm.errors.currency" class="text-sm text-destructive">{{ createForm.errors.currency }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="sort_order">Sort Order</Label>
                        <Input id="sort_order" v-model.number="createForm.sort_order" type="number" min="0" placeholder="0" />
                        <p v-if="createForm.errors.sort_order" class="text-sm text-destructive">{{ createForm.errors.sort_order }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <Label class="mb-2 block">Status</Label>
                        <Button
                            type="button"
                            variant="outline"
                            :class="createForm.is_active ? 'border-emerald-600 text-emerald-700' : 'border-border text-muted-foreground'"
                            @click="createForm.is_active = !createForm.is_active"
                        >
                            {{ createForm.is_active ? 'Active' : 'Inactive' }}
                        </Button>
                    </div>

                    <div class="md:col-span-2">
                        <Button type="submit" :disabled="createForm.processing">Create Pack</Button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="text-lg font-semibold">Existing Packs</h2>

                <div class="mt-4 space-y-4">
                    <article
                        v-for="pack in props.packs"
                        :key="pack.id"
                        class="rounded-lg border border-border/80 bg-muted/20 p-4"
                    >
                        <div class="grid gap-3 md:grid-cols-3">
                            <Input v-model="edits[pack.id].name" placeholder="Pack name" />
                            <Input v-model.number="edits[pack.id].credits" type="number" min="1" placeholder="Credits" />
                            <Input v-model.number="edits[pack.id].price_cents" type="number" min="1" placeholder="Price in cents" />
                            <Input v-model="edits[pack.id].currency" maxlength="3" placeholder="USD" />
                            <Input v-model.number="edits[pack.id].sort_order" type="number" min="0" placeholder="Sort order" />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :class="edits[pack.id].is_active ? 'border-emerald-600 text-emerald-700' : 'border-border text-muted-foreground'"
                                @click="edits[pack.id].is_active = !edits[pack.id].is_active"
                            >
                                {{ edits[pack.id].is_active ? 'Active' : 'Inactive' }}
                            </Button>
                        </div>
                        <Input
                            class="mt-3"
                            :model-value="edits[pack.id].description ?? ''"
                            placeholder="Description"
                            @update:model-value="(value) => (edits[pack.id].description = String(value))"
                        />

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm text-muted-foreground">
                                Preview: {{ edits[pack.id].credits }} credits for {{ formatPrice(edits[pack.id].price_cents, edits[pack.id].currency) }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Button type="button" size="sm" variant="outline" @click="savePack(pack.id)">
                                    Save
                                </Button>
                                <Button type="button" size="sm" variant="destructive" @click="deletePack(pack.id)">
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </article>

                    <p v-if="props.packs.length === 0" class="text-sm text-muted-foreground">
                        No packs created yet.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
