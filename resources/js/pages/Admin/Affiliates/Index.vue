<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Copy, Link2, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PartnerRow = {
    id: number;
    name: string | null;
    email: string;
    slug: string;
    is_active: boolean;
    notes: string | null;
    capture_url: string;
    stats_url: string | null;
    stats: {
        clicks: number;
        optins: number;
        sales: number;
    };
};

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedPartners = {
    data: PartnerRow[];
    links: PaginatorLink[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    partners: PaginatedPartners;
    filters: { q: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Affiliates', href: '/admin/affiliates' },
];

const q = ref(props.filters.q ?? '');
watch(
    () => props.filters.q,
    (value) => {
        q.value = value ?? '';
    },
);

const runSearch = useDebounceFn(() => {
    router.get('/admin/affiliates', { q: q.value.trim() || undefined }, { preserveState: true, replace: true });
}, 350);

watch(q, () => runSearch());

const createForm = useForm({
    name: '',
    email: '',
    slug: '',
    notes: '',
    is_active: true,
});

const submitCreate = () => {
    createForm.post('/admin/affiliates', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.is_active = true;
        },
    });
};

const toggleActive = (partner: PartnerRow) => {
    router.patch(
        `/admin/affiliates/${partner.slug}`,
        {
            name: partner.name ?? '',
            email: partner.email,
            slug: partner.slug,
            is_active: !partner.is_active,
            notes: partner.notes ?? '',
        },
        { preserveScroll: true },
    );
};

const copyUrl = async (url: string) => {
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // no-op
    }
};

const deletePartner = (partner: PartnerRow) => {
    const ok = window.confirm(`Delete affiliate partner "${partner.name || partner.slug}"? This will also remove their tracking history.`);
    if (!ok) return;

    router.delete(`/admin/affiliates/${partner.slug}`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Affiliates" />

        <div class="min-h-full bg-muted/30 dark:bg-muted/10">
            <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Affiliate Partners</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Create partner capture links and track clicks, opt-ins, and attributed sales.
                    </p>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <section class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border lg:col-span-1">
                        <h2 class="text-base font-semibold">Add Affiliate</h2>
                        <form class="mt-4 space-y-3" @submit.prevent="submitCreate">
                            <div>
                                <Label for="af-name">Name</Label>
                                <Input id="af-name" v-model="createForm.name" placeholder="John Affiliate" />
                                <p v-if="createForm.errors.name" class="mt-1 text-xs text-destructive">{{ createForm.errors.name }}</p>
                            </div>
                            <div>
                                <Label for="af-email">Email</Label>
                                <Input id="af-email" v-model="createForm.email" type="email" placeholder="john@example.com" />
                                <p v-if="createForm.errors.email" class="mt-1 text-xs text-destructive">{{ createForm.errors.email }}</p>
                            </div>
                            <div>
                                <Label for="af-slug">Slug (optional)</Label>
                                <Input id="af-slug" v-model="createForm.slug" placeholder="john-affiliate" />
                                <p v-if="createForm.errors.slug" class="mt-1 text-xs text-destructive">{{ createForm.errors.slug }}</p>
                            </div>
                            <div>
                                <Label for="af-notes">Notes (optional)</Label>
                                <textarea
                                    id="af-notes"
                                    v-model="createForm.notes"
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                    placeholder="Traffic source, budget, terms..."
                                />
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="createForm.is_active" type="checkbox" class="size-4 rounded border" />
                                Active
                            </label>
                            <Button type="submit" class="w-full bg-violet-600 text-white hover:bg-violet-700" :disabled="createForm.processing">
                                {{ createForm.processing ? 'Saving...' : 'Create Partner' }}
                            </Button>
                        </form>
                    </section>

                    <section class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border lg:col-span-2">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="text-base font-semibold">Partners</h2>
                            <div class="relative w-full max-w-xs">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input v-model="q" class="pl-9" type="search" placeholder="Search partner..." />
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-left text-sm">
                                <thead class="border-b border-border/60 bg-muted/30">
                                    <tr>
                                        <th class="px-3 py-2.5 font-semibold">Partner</th>
                                        <th class="px-3 py-2.5 font-semibold">Capture URL</th>
                                        <th class="px-3 py-2.5 font-semibold">Stats</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="partner in partners.data" :key="partner.id" class="border-b border-border/40 last:border-0">
                                        <td class="px-3 py-3 align-top">
                                            <p class="font-medium">{{ partner.name || partner.slug }}</p>
                                            <p class="text-xs text-muted-foreground">{{ partner.email }}</p>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            <div class="flex items-center gap-2">
                                                <a :href="partner.capture_url" target="_blank" rel="noopener" class="max-w-72 truncate text-xs text-indigo-600 hover:underline">
                                                    {{ partner.capture_url }}
                                                </a>
                                                <Button type="button" size="icon" variant="ghost" title="Copy capture url" @click="copyUrl(partner.capture_url)">
                                                    <Copy class="size-4" />
                                                </Button>
                                            </div>
                                            <div v-if="partner.stats_url" class="mt-1.5 flex items-center gap-2">
                                                <a :href="partner.stats_url" target="_blank" rel="noopener" class="max-w-72 truncate text-xs text-fuchsia-600 hover:underline">
                                                    Stats page ↗
                                                </a>
                                                <Button type="button" size="icon" variant="ghost" title="Copy stats url" @click="copyUrl(partner.stats_url!)">
                                                    <Copy class="size-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 align-top text-xs">
                                            <p>Clicks: <span class="font-semibold">{{ partner.stats.clicks }}</span></p>
                                            <p>Opt-ins: <span class="font-semibold">{{ partner.stats.optins }}</span></p>
                                            <p>Sales: <span class="font-semibold">{{ partner.stats.sales }}</span></p>
                                        </td>
                                        <td class="px-3 py-3 align-top text-right">
                                            <div class="flex flex-col items-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    :variant="partner.is_active ? 'secondary' : 'outline'"
                                                    @click="toggleActive(partner)"
                                                >
                                                    <Link2 class="mr-1 size-3.5" />
                                                    {{ partner.is_active ? 'Active' : 'Inactive' }}
                                                </Button>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    class="border-destructive/40 text-destructive hover:bg-destructive/10"
                                                    @click="deletePartner(partner)"
                                                >
                                                    <Trash2 class="mr-1 size-3.5" />
                                                    Delete
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="partners.total > 0" class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                            <p v-if="partners.from != null && partners.to != null">
                                Showing {{ partners.from }}-{{ partners.to }} of {{ partners.total }}
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <Link
                                    v-for="link in partners.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    preserve-scroll
                                    class="inline-flex min-w-8 items-center justify-center rounded-md border border-border px-2.5 py-1 transition hover:bg-muted"
                                    :class="{
                                        'border-violet-500 bg-violet-600 text-white hover:bg-violet-600': link.active,
                                        'pointer-events-none opacity-40': !link.url,
                                    }"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
