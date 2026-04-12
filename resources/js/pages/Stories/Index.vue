<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    CheckCircle2,
    FileText,
    Filter,
    Loader2,
    Plus,
    Search,
    Trash2,
    Video,
    Volume2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { normalizeLaravelPaginator } from '@/lib/normalizeLaravelPaginator';
import type { BreadcrumbItem } from '@/types';

type ProjectRow = {
    id: number;
    uuid: string;
    title: string;
    topic: string;
    status: string;
    page_count: number;
    pages_completed: number;
    include_narration: boolean;
    include_video: boolean;
    cover_url: string | null;
    created_at: string;
    updated_at: string;
};

type Stats = {
    total: number;
    ready: number;
    processing: number;
    draft: number;
    failed: number;
};

const props = defineProps<{
    /** Laravel LengthAwarePaginator JSON (flat fields) or legacy array of rows */
    projects: unknown;
    stats: Stats;
    filters: { status?: string; search?: string; date_from?: string; date_to?: string; page?: string; per_page?: string };
}>();

const projectsPage = computed(() => normalizeLaravelPaginator<ProjectRow>(props.projects));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Stories', href: '/stories' }];

const selected = ref<number[]>([]);
const search = ref(props.filters.search ?? '');
const filterStatus = ref(props.filters.status ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let searchTimer: ReturnType<typeof setTimeout>;

function applyFilters() {
    router.get(
        '/stories',
        {
            search: search.value || undefined,
            status: filterStatus.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 400);
});

watch([filterStatus, dateFrom, dateTo], applyFilters);

function clearFilters() {
    search.value = '';
    filterStatus.value = '';
    dateFrom.value = '';
    dateTo.value = '';
}

const hasActiveFilters = computed(() =>
    search.value || filterStatus.value || dateFrom.value || dateTo.value,
);

function toggleSelect(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((x) => x !== id)
        : [...selected.value, id];
}

function selectAll() {
    const rows = projectsPage.value.data;
    selected.value =
        selected.value.length === rows.length
            ? []
            : rows.map((p) => p.id);
}

const allSelected = computed(
    () => projectsPage.value.data.length > 0 && selected.value.length === projectsPage.value.data.length,
);
const someSelected = computed(
    () => selected.value.length > 0 && selected.value.length < projectsPage.value.data.length,
);

function deleteStory(uuid: string) {
    if (confirm('Delete this story? This cannot be undone.')) {
        router.delete(`/stories/${uuid}`);
    }
}

function bulkDelete() {
    if (selected.value.length === 0) return;
    if (confirm(`Permanently delete ${selected.value.length} selected story/stories?`)) {
        router.post('/stories/bulk-destroy', { ids: selected.value });
        selected.value = [];
    }
}

function goStoriesPage(page: number): void {
    if (page < 1 || page > projectsPage.value.meta.last_page) {
        return;
    }

    router.get(
        '/stories',
        {
            search: search.value || undefined,
            status: filterStatus.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function statusColor(status: string) {
    switch (status) {
        case 'ready':      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400';
        case 'processing': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400';
        case 'draft':      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400';
        case 'failed':     return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400';
        default:           return 'bg-muted text-muted-foreground';
    }
}

function progressPercent(p: ProjectRow) {
    return p.page_count ? Math.round((p.pages_completed / p.page_count) * 100) : 0;
}
</script>

<template>
    <Head title="Stories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Story Projects</h1>
                    <p class="text-muted-foreground text-sm mt-0.5">
                        Manage and track all your AI storybooks
                    </p>
                </div>
                <Button as-child>
                    <Link href="/stories/create" class="flex items-center gap-2">
                        <Plus class="size-4" />
                        New Story
                    </Link>
                </Button>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <button
                    class="flex flex-col items-start gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 text-left transition-shadow hover:shadow-md dark:border-sidebar-border"
                    :class="filterStatus === '' ? 'ring-2 ring-violet-400' : ''"
                    @click="filterStatus = ''"
                >
                    <span class="text-muted-foreground text-xs font-medium uppercase tracking-wide">All</span>
                    <span class="text-2xl font-bold">{{ stats.total }}</span>
                </button>
                <button
                    class="flex flex-col items-start gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 text-left transition-shadow hover:shadow-md dark:border-sidebar-border"
                    :class="filterStatus === 'ready' ? 'ring-2 ring-emerald-400' : ''"
                    @click="filterStatus = filterStatus === 'ready' ? '' : 'ready'"
                >
                    <span class="text-xs font-medium uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Ready</span>
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.ready }}</span>
                </button>
                <button
                    class="flex flex-col items-start gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 text-left transition-shadow hover:shadow-md dark:border-sidebar-border"
                    :class="filterStatus === 'processing' ? 'ring-2 ring-blue-400' : ''"
                    @click="filterStatus = filterStatus === 'processing' ? '' : 'processing'"
                >
                    <span class="text-xs font-medium uppercase tracking-wide text-blue-600 dark:text-blue-400">Processing</span>
                    <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.processing }}</span>
                </button>
                <button
                    class="flex flex-col items-start gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 text-left transition-shadow hover:shadow-md dark:border-sidebar-border"
                    :class="filterStatus === 'draft' ? 'ring-2 ring-amber-400' : ''"
                    @click="filterStatus = filterStatus === 'draft' ? '' : 'draft'"
                >
                    <span class="text-xs font-medium uppercase tracking-wide text-amber-600 dark:text-amber-400">Draft</span>
                    <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.draft }}</span>
                </button>
                <button
                    class="flex flex-col items-start gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 text-left transition-shadow hover:shadow-md dark:border-sidebar-border"
                    :class="filterStatus === 'failed' ? 'ring-2 ring-red-400' : ''"
                    @click="filterStatus = filterStatus === 'failed' ? '' : 'failed'"
                >
                    <span class="text-xs font-medium uppercase tracking-wide text-red-600 dark:text-red-400">Failed</span>
                    <span class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.failed }}</span>
                </button>
            </div>

            <!-- Toolbar: search, filters, bulk delete -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Search -->
                <div class="relative min-w-48 flex-1">
                    <Search class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search title or topic…"
                        class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-lg border py-2 pl-9 pr-4 text-sm focus-visible:outline-none focus-visible:ring-1"
                    />
                </div>

                <!-- Date from -->
                <div class="flex items-center gap-1.5">
                    <Filter class="text-muted-foreground size-4 shrink-0" />
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="border-input bg-background focus-visible:ring-ring rounded-lg border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1"
                        title="From date"
                    />
                    <span class="text-muted-foreground text-xs">—</span>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="border-input bg-background focus-visible:ring-ring rounded-lg border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1"
                        title="To date"
                    />
                </div>

                <!-- Clear filters -->
                <button
                    v-if="hasActiveFilters"
                    class="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-sm"
                    @click="clearFilters"
                >
                    <XCircle class="size-4" />
                    Clear
                </button>

                <div class="flex-1" />

                <!-- Bulk delete -->
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="selected.length === 0"
                    class="flex items-center gap-2"
                    @click="bulkDelete"
                >
                    <Trash2 class="size-4" />
                    Delete{{ selected.length > 0 ? ` (${selected.length})` : '' }}
                </Button>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">

                <!-- Table header -->
                <div class="bg-muted/40 border-b px-4 py-3">
                    <div class="grid grid-cols-[auto_3rem_1fr_auto_auto_auto_auto] items-center gap-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <input
                            type="checkbox"
                            class="size-4 rounded"
                            :checked="allSelected"
                            :indeterminate="someSelected"
                            @change="selectAll"
                        />
                        <span>Cover</span>
                        <span>Story</span>
                        <span class="text-center">Status</span>
                        <span class="text-center">Progress</span>
                        <span class="text-center">Features</span>
                        <span class="text-right">Actions</span>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="projectsPage.data.length === 0"
                    class="flex flex-col items-center gap-4 py-20 text-center"
                >
                    <div class="flex size-14 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/30">
                        <BookOpen class="size-6 text-violet-500" />
                    </div>
                    <div>
                        <p class="font-medium">
                            {{ hasActiveFilters ? 'No stories match your filters' : 'No stories yet' }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ hasActiveFilters ? 'Try adjusting the search or filters.' : 'Create your first AI-powered storybook.' }}
                        </p>
                    </div>
                    <Button v-if="!hasActiveFilters" as-child size="sm">
                        <Link href="/stories/create" class="flex items-center gap-2">
                            <Plus class="size-4" />
                            Create first story
                        </Link>
                    </Button>
                    <button v-else class="text-sm text-violet-600 hover:underline" @click="clearFilters">
                        Clear filters
                    </button>
                </div>

                <!-- Rows -->
                <div
                    v-for="p in projectsPage.data"
                    :key="p.uuid"
                    class="group grid grid-cols-[auto_3rem_1fr_auto_auto_auto_auto] items-center gap-3 border-b px-4 py-3 text-sm last:border-0 hover:bg-muted/30 transition-colors"
                    :class="selected.includes(p.id) ? 'bg-violet-50/60 dark:bg-violet-950/20' : ''"
                >
                    <!-- Checkbox -->
                    <input
                        type="checkbox"
                        class="size-4 rounded"
                        :checked="selected.includes(p.id)"
                        @change="toggleSelect(p.id)"
                    />

                    <!-- Cover thumbnail -->
                    <div class="size-10 shrink-0 overflow-hidden rounded-md border border-sidebar-border/50 bg-muted">
                        <img
                            v-if="p.cover_url"
                            :src="p.cover_url"
                            :alt="`${p.title} cover`"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <FileText class="text-muted-foreground size-4" />
                        </div>
                    </div>

                    <!-- Title + meta -->
                    <div class="min-w-0 flex flex-col gap-0.5">
                        <Link
                            :href="`/stories/${p.uuid}`"
                            class="truncate font-semibold hover:underline hover:text-violet-600"
                        >
                            {{ p.title }}
                        </Link>
                        <span class="text-muted-foreground truncate text-xs">{{ p.topic }}</span>
                        <span class="text-muted-foreground text-xs">{{ p.created_at }}</span>
                    </div>

                    <!-- Status badge -->
                    <div class="flex justify-center">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                            :class="statusColor(p.status)"
                        >
                            {{ p.status }}
                        </span>
                    </div>

                    <!-- Progress -->
                    <div class="flex w-28 flex-col gap-1">
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>{{ p.pages_completed }}/{{ p.page_count }}</span>
                            <span>{{ progressPercent(p) }}%</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="p.status === 'ready' ? 'bg-emerald-500' : p.status === 'failed' ? 'bg-red-400' : 'bg-violet-500'"
                                :style="{ width: `${progressPercent(p)}%` }"
                            />
                        </div>
                    </div>

                    <!-- Features icons -->
                    <div class="flex items-center justify-center gap-2">
                        <span
                            :class="p.include_narration ? 'text-blue-500' : 'text-muted-foreground/30'"
                            :title="p.include_narration ? 'Audio narration' : 'No narration'"
                        >
                            <Volume2 class="size-4" />
                        </span>
                        <span
                            :class="p.include_video ? 'text-violet-500' : 'text-muted-foreground/30'"
                            :title="p.include_video ? 'Video' : 'No video'"
                        >
                            <Video class="size-4" />
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/stories/${p.uuid}`" class="flex items-center gap-1.5">
                                <BookOpen class="size-3.5" />
                                Open
                            </Link>
                        </Button>
                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive hover:bg-destructive/10 px-2" @click="deleteStory(p.uuid)">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="projectsPage.meta.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-2 pt-2"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="projectsPage.meta.current_page <= 1"
                    @click="goStoriesPage(projectsPage.meta.current_page - 1)"
                >
                    Previous
                </Button>
                <span class="text-muted-foreground text-sm">
                    Page {{ projectsPage.meta.current_page }} of {{ projectsPage.meta.last_page }}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="projectsPage.meta.current_page >= projectsPage.meta.last_page"
                    @click="goStoriesPage(projectsPage.meta.current_page + 1)"
                >
                    Next
                </Button>
            </div>

            <!-- Footer count -->
            <p v-if="projectsPage.data.length > 0" class="text-muted-foreground text-xs text-right">
                Showing {{ projectsPage.meta.from ?? 0 }}–{{ projectsPage.meta.to ?? 0 }} of {{ projectsPage.meta.total }} stories
            </p>
        </div>
    </AppLayout>
</template>
