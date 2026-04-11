<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
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
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type AccountRow = {
    id: number;
    name: string;
    email: string;
    story_credits: number;
    created_at: string | null;
};

const props = defineProps<{
    accounts: AccountRow[];
    maxSubAccounts: number;
    subAccountCredits: number;
    currentCount: number;
}>();

const page = usePage<{ flash?: { success?: string | null } }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Reseller', href: '/reseller' },
];

const createOpen = ref(false);

const form = useForm({
    name: '',
    email: '',
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
};

const submitCreate = () => {
    form.post('/reseller/accounts', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            createOpen.value = false;
        },
    });
};

const atLimit = () => props.currentCount >= props.maxSubAccounts;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Reseller — create accounts" />

        <div class="mx-auto max-w-4xl space-y-6 p-4 md:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Reseller seats
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-muted-foreground">
                        Create separate logins for clients, family, or buyers. Each account gets
                        {{ subAccountCredits }} starter credits on the Basic tier and receives an email with a
                        temporary password.
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ currentCount }} / {{ maxSubAccounts }} accounts used
                    </p>
                </div>
                <Button
                    type="button"
                    class="shrink-0 gap-2"
                    :disabled="atLimit() || form.processing"
                    @click="openCreate"
                >
                    <UserPlus class="size-4" />
                    Create account
                </Button>
            </div>

            <div
                v-if="page.props.flash?.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.success }}
            </div>

            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-foreground">Accounts you created</h2>
                </div>
                <div v-if="accounts.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                    No sub-accounts yet. Use Create account to add one.
                </div>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="row in accounts"
                        :key="row.id"
                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-medium text-foreground">{{ row.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ row.email }}</p>
                        </div>
                        <div class="text-xs text-muted-foreground sm:text-right">
                            <p>{{ row.story_credits }} credits</p>
                            <p v-if="row.created_at">{{ row.created_at }}</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <Dialog v-model:open="createOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create sub-account</DialogTitle>
                    <DialogDescription>
                        We will email them a sign-in link and a one-time temporary password.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="space-y-2">
                        <Label for="reseller-name">Full name</Label>
                        <Input
                            id="reseller-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            required
                        />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="reseller-email">Email</Label>
                        <Input
                            id="reseller-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                        />
                        <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" :disabled="form.processing" @click="createOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Creating…' : 'Create & email' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
