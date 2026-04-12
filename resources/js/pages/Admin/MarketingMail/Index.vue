<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { useDebounceFn } from '@vueuse/core';
import { Mail, Search, Send, UserPlus, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type UserPick = {
    id: number;
    name: string;
    email: string;
};

const props = defineProps<{
    maxRecipients: number;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Marketing email', href: '/admin/marketing-mail' },
];

const searchQ = ref('');
const searchBusy = ref(false);
const searchResults = ref<UserPick[]>([]);
const selectedUsers = ref<UserPick[]>([]);
const extraEmails = ref('');
const editorKey = ref(0);

const toolbarOptions = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ align: [] }],
    ['link', 'blockquote', 'code-block'],
    ['clean'],
];

const form = useForm({
    subject: '',
    body_html: '<p>Hi there,</p><p><br></p><p>We would love for you to try DreamForge AI and create beautiful AI storybooks.</p>',
    user_ids: [] as number[],
    extra_emails: '',
});

const recipientCount = computed(() => {
    const ids = new Set(selectedUsers.value.map((u) => u.id));
    const pasted = extraEmails.value
        .split(',')
        .map((s) => s.trim().toLowerCase())
        .filter(Boolean);
    const uniquePaste = new Set(pasted);
    return ids.size + uniquePaste.size;
});

const canSubmit = computed(
    () =>
        form.subject.trim().length > 0 &&
        recipientCount.value > 0 &&
        recipientCount.value <= props.maxRecipients &&
        !form.processing,
);

async function runUserSearch(): Promise<void> {
    searchBusy.value = true;
    try {
        const q = searchQ.value.trim();
        const url = `/admin/marketing-mail/user-search?q=${encodeURIComponent(q)}`;
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            searchResults.value = [];

            return;
        }
        const data = (await res.json()) as { users: UserPick[] };
        searchResults.value = data.users ?? [];
    } catch {
        searchResults.value = [];
    } finally {
        searchBusy.value = false;
    }
}

const debouncedSearch = useDebounceFn(runUserSearch, 350);

watch(searchQ, () => {
    void debouncedSearch();
});

onMounted(() => {
    void runUserSearch();
});

function addUser(u: UserPick): void {
    if (selectedUsers.value.some((x) => x.id === u.id)) {
        return;
    }

    selectedUsers.value = [...selectedUsers.value, u];
}

function removeUser(id: number): void {
    selectedUsers.value = selectedUsers.value.filter((u) => u.id !== id);
}

function submit(): void {
    form.user_ids = selectedUsers.value.map((u) => u.id);
    form.extra_emails = extraEmails.value;
    form.post('/admin/marketing-mail/send', {
        preserveScroll: true,
        onSuccess: () => {
            selectedUsers.value = [];
            extraEmails.value = '';
            const defaultBody =
                '<p>Hi there,</p><p><br></p><p>We would love for you to try DreamForge AI and create beautiful AI storybooks.</p>';
            form.defaults({
                subject: '',
                body_html: defaultBody,
                user_ids: [],
                extra_emails: '',
            });
            form.reset();
            form.body_html = defaultBody;
            editorKey.value += 1;
        },
    });
}
</script>

<template>
    <Head title="Marketing email" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-4xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-violet-600 dark:text-violet-400">
                        <Mail class="size-5" />
                        <span class="text-sm font-semibold uppercase tracking-wide">Admin</span>
                    </div>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Marketing email</h1>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Compose a rich HTML message, choose registered users and/or paste extra addresses, then queue
                        sends through the worker. Recipients are de-duplicated (max {{ maxRecipients }} per send).
                    </p>
                </div>
            </div>

            <div
                v-if="page.props.flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                {{ page.props.flash.error }}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <UserPlus class="size-5 text-violet-500" />
                        Recipients
                    </CardTitle>
                    <CardDescription> Search users by name or email, then add them. Optionally add more addresses
                        (comma-separated). </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-2">
                        <Label for="user-search">Find users</Label>
                        <div class="relative">
                            <Search class="text-muted-foreground pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2" />
                            <Input
                                id="user-search"
                                v-model="searchQ"
                                type="search"
                                placeholder="Type name or email…"
                                class="pl-9"
                                autocomplete="off"
                            />
                        </div>
                        <div
                            v-if="searchResults.length"
                            class="max-h-48 overflow-y-auto rounded-lg border border-border bg-card text-sm shadow-sm"
                        >
                            <button
                                v-for="u in searchResults"
                                :key="u.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 border-b border-border/60 px-3 py-2.5 text-left transition hover:bg-muted/60 last:border-0"
                                @click="addUser(u)"
                            >
                                <span>
                                    <span class="font-medium">{{ u.name }}</span>
                                    <span class="text-muted-foreground block text-xs">{{ u.email }}</span>
                                </span>
                                <span class="text-violet-600 text-xs font-semibold">Add</span>
                            </button>
                        </div>
                        <p v-else-if="searchBusy" class="text-muted-foreground text-xs">Searching…</p>
                    </div>

                    <div v-if="selectedUsers.length" class="space-y-2">
                        <Label>Selected users ({{ selectedUsers.length }})</Label>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="u in selectedUsers"
                                :key="u.id"
                                class="inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50 py-1 pl-3 pr-1 text-xs font-medium text-violet-900 dark:border-violet-800/60 dark:bg-violet-950/50 dark:text-violet-100"
                            >
                                {{ u.email }}
                                <button
                                    type="button"
                                    class="rounded-full p-0.5 hover:bg-violet-200/80 dark:hover:bg-violet-800/60"
                                    :aria-label="`Remove ${u.email}`"
                                    @click="removeUser(u.id)"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="extra-emails">Extra email addresses</Label>
                        <textarea
                            id="extra-emails"
                            v-model="extraEmails"
                            rows="3"
                            placeholder="one@example.com, other@example.com"
                            class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full resize-y rounded-lg border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1"
                        />
                    </div>

                    <p class="text-muted-foreground text-xs">
                        Total unique recipients for this send:
                        <span class="text-foreground font-semibold">{{ recipientCount }}</span>
                        / {{ maxRecipients }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Message</CardTitle>
                    <CardDescription> Subject line and rich body. Formatting is preserved in the email template. </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="subject">Subject</Label>
                        <Input id="subject" v-model="form.subject" type="text" placeholder="Try DreamForge AI today" />
                        <p v-if="form.errors.subject" class="text-destructive text-xs">{{ form.errors.subject }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Email body</Label>
                        <div class="overflow-hidden rounded-lg border border-border bg-background">
                            <QuillEditor
                                :key="editorKey"
                                v-model:content="form.body_html"
                                content-type="html"
                                theme="snow"
                                :toolbar="toolbarOptions"
                                class="marketing-quill min-h-[220px]"
                            />
                        </div>
                        <p v-if="form.errors.body_html" class="text-destructive text-xs">{{ form.errors.body_html }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p v-if="form.errors.recipients" class="text-destructive text-sm">{{ form.errors.recipients }}</p>
                <p v-if="form.errors.extra_emails" class="text-destructive text-sm">{{ form.errors.extra_emails }}</p>
                <Button
                    type="button"
                    class="ml-auto gap-2 bg-violet-600 text-white hover:bg-violet-700"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    <Send class="size-4" />
                    {{ form.processing ? 'Queueing…' : 'Send (queue)' }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.marketing-quill :deep(.ql-container) {
    min-height: 200px;
    font-size: 0.95rem;
}
</style>
