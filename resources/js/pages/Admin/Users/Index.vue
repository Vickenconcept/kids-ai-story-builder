<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Pencil, Search, Trash2, Users } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type UserRow = {
    id: number;
    uuid: string | null;
    name: string;
    email: string;
    story_credits: number;
    feature_tier: string;
    created_at: string | null;
};

type TierOption = { value: string; label: string };

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: UserRow[];
    links: PaginatorLink[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    users: PaginatedUsers;
    filters: { q: string };
    featureTiers: TierOption[];
}>();

const page = usePage();
const currentUserId = page.props.auth?.user?.id as number | undefined;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: '/admin/users' },
];

const q = ref(props.filters.q ?? '');
const creditDrafts = reactive<Record<number, number>>({});
const editOpen = ref(false);
const editingUser = ref<UserRow | null>(null);

watch(
    () => props.users.data,
    (rows) => {
        for (const u of rows) {
            creditDrafts[u.id] = u.story_credits;
        }
    },
    { immediate: true },
);

watch(
    () => props.filters.q,
    (v) => {
        q.value = v ?? '';
    },
);

const runSearch = useDebounceFn(() => {
    router.get(
        '/admin/users',
        { q: q.value.trim() || undefined },
        { preserveState: true, replace: true },
    );
}, 350);

watch(q, () => runSearch());

const editForm = useForm({
    name: '',
    email: '',
    story_credits: 0,
    feature_tier: 'basic',
    password: '',
    password_confirmation: '',
});

const openEdit = (user: UserRow) => {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.story_credits = user.story_credits;
    editForm.feature_tier = user.feature_tier;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.clearErrors();
    editOpen.value = true;
};

const submitEdit = () => {
    if (!editingUser.value) {
        return;
    }
    editForm.patch(`/admin/users/${editingUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
        },
    });
};

const saveCredits = (user: UserRow) => {
    const credits = creditDrafts[user.id] ?? user.story_credits;
    router.patch(
        `/admin/users/${user.id}`,
        {
            name: user.name,
            email: user.email,
            story_credits: credits,
            feature_tier: user.feature_tier,
        },
        { preserveScroll: true },
    );
};

const deleteUser = (user: UserRow) => {
    if (user.id === currentUserId) {
        return;
    }
    if (!confirm(`Delete ${user.email}? This removes their stories and related data.`)) {
        return;
    }
    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
};

const tierLabel = (value: string) =>
    props.featureTiers.find((t) => t.value === value)?.label ?? value;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Users" />

        <div class="min-h-full bg-muted/30 dark:bg-muted/10">
            <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Users</h1>
                        <p class="text-muted-foreground mt-0.5 text-sm">
                            Search, edit plans and credits, and remove accounts (admin only).
                        </p>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-900/30 dark:text-violet-300"
                    >
                        <Users class="size-3.5" />
                        Admin
                    </span>
                </div>

                <div class="relative max-w-md">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2"
                    />
                    <Input v-model="q" class="pl-9" placeholder="Search by name or email…" type="search" />
                </div>

                <div class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead class="border-b border-border/60 bg-muted/40">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">User</th>
                                    <th class="px-4 py-3 font-semibold">Plan</th>
                                    <th class="w-44 px-4 py-3 font-semibold">Credits</th>
                                    <th class="w-36 px-4 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.id" class="border-b border-border/40 last:border-0">
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium">{{ user.name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ user.email }}</p>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <span
                                            class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium capitalize text-violet-800 dark:bg-violet-900/40 dark:text-violet-200"
                                        >
                                            {{ tierLabel(user.feature_tier) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                            <Input
                                                v-model.number="creditDrafts[user.id]"
                                                class="h-9 w-full sm:max-w-28"
                                                min="0"
                                                type="number"
                                            />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                class="shrink-0"
                                                @click="saveCredits(user)"
                                            >
                                                Save
                                            </Button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button type="button" size="icon" variant="ghost" title="Edit user" @click="openEdit(user)">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                class="text-destructive hover:text-destructive"
                                                :disabled="user.id === currentUserId"
                                                :title="user.id === currentUserId ? 'Cannot delete yourself' : 'Delete user'"
                                                @click="deleteUser(user)"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="users.data.length === 0"
                        class="flex flex-col items-center gap-2 px-4 py-14 text-center text-sm text-muted-foreground"
                    >
                        <Users class="size-10 opacity-40" />
                        <p>No users match your search.</p>
                    </div>

                    <div
                        v-if="users.total > 0"
                        class="flex flex-col gap-3 border-t border-border/60 px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p v-if="users.from != null && users.to != null">
                            Showing {{ users.from }}–{{ users.to }} of {{ users.total }}
                        </p>
                        <div class="flex flex-wrap justify-center gap-1 sm:justify-end">
                            <Link
                                v-for="link in users.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="inline-flex min-w-8 items-center justify-center rounded-md border border-border px-2.5 py-1 transition hover:bg-muted"
                                :class="{
                                    'border-violet-500 bg-violet-600 text-white hover:bg-violet-600': link.active,
                                    'pointer-events-none opacity-40': !link.url,
                                }"
                                preserve-scroll
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="editOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit user</DialogTitle>
                    <DialogDescription>Update profile, plan, credits, or set a new password.</DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="space-y-1.5">
                        <Label for="edit-name">Name</Label>
                        <Input id="edit-name" v-model="editForm.name" autocomplete="name" />
                        <p v-if="editForm.errors.name" class="text-xs text-destructive">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit-email">Email</Label>
                        <Input id="edit-email" v-model="editForm.email" type="email" autocomplete="email" />
                        <p v-if="editForm.errors.email" class="text-xs text-destructive">{{ editForm.errors.email }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit-credits">Credits</Label>
                        <Input id="edit-credits" v-model.number="editForm.story_credits" min="0" type="number" />
                        <p v-if="editForm.errors.story_credits" class="text-xs text-destructive">
                            {{ editForm.errors.story_credits }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit-tier">Plan</Label>
                        <select
                            id="edit-tier"
                            v-model="editForm.feature_tier"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <option v-for="t in featureTiers" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="editForm.errors.feature_tier" class="text-xs text-destructive">
                            {{ editForm.errors.feature_tier }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit-password">New password</Label>
                        <PasswordInput id="edit-password" v-model="editForm.password" autocomplete="new-password" placeholder="Leave blank to keep current" />
                        <p v-if="editForm.errors.password" class="text-xs text-destructive">{{ editForm.errors.password }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit-password-confirmation">Confirm password</Label>
                        <PasswordInput
                            id="edit-password-confirmation"
                            v-model="editForm.password_confirmation"
                            autocomplete="new-password"
                        />
                        <p v-if="editForm.errors.password_confirmation" class="text-xs text-destructive">
                            {{ editForm.errors.password_confirmation }}
                        </p>
                    </div>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="secondary" @click="editOpen = false">Cancel</Button>
                        <Button type="submit" class="bg-violet-600 text-white hover:bg-violet-700" :disabled="editForm.processing">
                            {{ editForm.processing ? 'Saving…' : 'Save changes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
