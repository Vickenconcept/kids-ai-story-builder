<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    CheckCircle2,
    ChevronRight,
    CreditCard,
    Loader2,
    Plus,
    Sparkles,
    Video,
    Volume2,
    Zap,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/vue3';

type RecentProject = {
    uuid: string;
    title: string;
    topic: string;
    status: string;
    page_count: number;
    pages_completed: number;
    include_video: boolean;
    include_narration: boolean;
    created_at: string;
};

type Stats = {
    total: number;
    ready: number;
    processing: number;
    draft: number;
    failed: number;
};

const props = defineProps<{
    stats: Stats;
    recentProjects: RecentProject[];
    credits: number;
    tier: string;
}>();

const page = usePage<{ auth: { user: { name: string } } }>();
const userName = page.props.auth?.user?.name ?? 'there';
const firstName = userName.split(' ')[0];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

function statusColor(status: string) {
    switch (status) {
        case 'ready':      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400';
        case 'processing': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400';
        case 'draft':      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400';
        case 'failed':     return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400';
        default:           return 'bg-muted text-muted-foreground';
    }
}

function statusLabel(status: string) {
    switch (status) {
        case 'ready':      return 'Ready';
        case 'processing': return 'Processing';
        case 'draft':      return 'Draft';
        case 'failed':     return 'Failed';
        default:           return status;
    }
}

function tierColor(tier: string) {
    switch (tier) {
        case 'pro':   return 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400';
        case 'elite': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400';
        default:      return 'bg-muted text-muted-foreground';
    }
}

function progressPercent(project: RecentProject) {
    if (!project.page_count) return 0;
    return Math.round((project.pages_completed / project.page_count) * 100);
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">

            <!-- Welcome hero -->
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-gradient-to-br from-violet-50 to-indigo-50 p-6 dark:from-violet-950/30 dark:to-indigo-950/30 dark:border-sidebar-border">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Sparkles class="size-5 text-violet-500" />
                        <span class="text-sm font-medium text-violet-600 dark:text-violet-400">StorySpark AI</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                            :class="tierColor(tier)"
                        >
                            {{ tier }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Welcome back, {{ firstName }}!
                    </h1>
                    <p class="text-muted-foreground text-sm">
                        Your AI-powered storybook creation studio. Create, illustrate, narrate and flip.
                    </p>
                </div>
                <div class="flex gap-3">
                    <Button as-child variant="outline" size="sm">
                        <Link href="/stories" class="flex items-center gap-2">
                            <BookOpen class="size-4" />
                            My Stories
                        </Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link href="/stories/create" class="flex items-center gap-2">
                            <Plus class="size-4" />
                            New Story
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Total Stories</CardTitle>
                        <BookOpen class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.total }}</p>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {{ stats.draft }} draft Â· {{ stats.failed }} failed
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Completed</CardTitle>
                        <CheckCircle2 class="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.ready }}</p>
                        <p class="text-muted-foreground mt-0.5 text-xs">Stories fully generated</p>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Processing</CardTitle>
                        <Loader2 class="size-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ stats.processing }}</p>
                        <p class="text-muted-foreground mt-0.5 text-xs">AI pipeline running</p>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Story Credits</CardTitle>
                        <Zap class="size-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ credits }}</p>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            <Link href="/credits" class="hover:underline">Top up credits</Link>
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Main content: recent stories + quick actions -->
            <div class="grid gap-6 lg:grid-cols-3">

                <!-- Recent stories (2/3 width) -->
                <div class="lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">Recent Stories</h2>
                        <Link href="/stories" class="text-muted-foreground flex items-center gap-1 text-sm hover:underline">
                            View all <ChevronRight class="size-3.5" />
                        </Link>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="recentProjects.length === 0"
                        class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border"
                    >
                        <div class="flex size-14 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/30">
                            <Sparkles class="size-6 text-violet-500" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-medium">No stories yet</p>
                            <p class="text-muted-foreground text-sm">Create your first AI-powered storybook in seconds.</p>
                        </div>
                        <Button as-child size="sm">
                            <Link href="/stories/create" class="flex items-center gap-2">
                                <Plus class="size-4" />
                                Create first story
                            </Link>
                        </Button>
                    </div>

                    <!-- Story cards -->
                    <div v-else class="flex flex-col gap-3">
                        <Link
                            v-for="project in recentProjects"
                            :key="project.uuid"
                            :href="`/stories/${project.uuid}`"
                            class="group flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition-shadow hover:shadow-md dark:border-sidebar-border"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex flex-col gap-0.5">
                                    <p class="line-clamp-1 font-medium group-hover:underline">{{ project.title }}</p>
                                    <p class="text-muted-foreground line-clamp-1 text-xs">{{ project.topic }}</p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                                        :class="statusColor(project.status)"
                                    >
                                        {{ statusLabel(project.status) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{{ project.pages_completed }} / {{ project.page_count }} pages</span>
                                    <span>{{ progressPercent(project) }}%</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-violet-500 transition-all"
                                        :style="{ width: `${progressPercent(project)}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Meta row -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                    <span v-if="project.include_narration" class="flex items-center gap-1">
                                        <Volume2 class="size-3" /> Audio
                                    </span>
                                    <span v-if="project.include_video" class="flex items-center gap-1">
                                        <Video class="size-3" /> Video
                                    </span>
                                </div>
                                <span class="text-xs text-muted-foreground">{{ project.created_at }}</span>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Quick actions sidebar (1/3 width) -->
                <div class="flex flex-col gap-4">
                    <h2 class="font-semibold">Quick Actions</h2>

                    <div class="flex flex-col gap-3">
                        <Link
                            href="/stories/create"
                            class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition-shadow hover:shadow-md dark:border-sidebar-border"
                        >
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/30">
                                <Plus class="size-5 text-violet-600 dark:text-violet-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">New Story</p>
                                <p class="text-muted-foreground text-xs">Generate a full AI storybook</p>
                            </div>
                        </Link>

                        <Link
                            href="/stories"
                            class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition-shadow hover:shadow-md dark:border-sidebar-border"
                        >
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <BookOpen class="size-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">My Stories</p>
                                <p class="text-muted-foreground text-xs">Browse all your storybooks</p>
                            </div>
                        </Link>

                        <Link
                            href="/credits"
                            class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition-shadow hover:shadow-md dark:border-sidebar-border"
                        >
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                                <CreditCard class="size-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">Buy Credits</p>
                                <p class="text-muted-foreground text-xs">{{ credits }} credits remaining</p>
                            </div>
                        </Link>
                    </div>

                    <!-- Tier upgrade nudge for basic users -->
                    <div
                        v-if="tier === 'basic'"
                        class="rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50 to-indigo-50 p-4 dark:border-violet-800/40 dark:from-violet-950/30 dark:to-indigo-950/30"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <Sparkles class="size-4 text-violet-500" />
                            <p class="text-sm font-semibold text-violet-700 dark:text-violet-300">Upgrade to Pro</p>
                        </div>
                        <p class="text-xs text-muted-foreground mb-3">
                            Unlock video generation, longer stories, priority processing and more.
                        </p>
                        <Button as-child size="sm" class="w-full bg-violet-600 hover:bg-violet-700 text-white">
                            <Link href="/oto1">Upgrade Now</Link>
                        </Button>
                    </div>

                    <!-- Stats breakdown card -->
                    <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Story Overview</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-muted-foreground">
                                    <span class="inline-block size-2 rounded-full bg-emerald-500" /> Completed
                                </span>
                                <span class="font-semibold">{{ stats.ready }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-muted-foreground">
                                    <span class="inline-block size-2 rounded-full bg-blue-500" /> Processing
                                </span>
                                <span class="font-semibold">{{ stats.processing }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-muted-foreground">
                                    <span class="inline-block size-2 rounded-full bg-amber-500" /> Draft
                                </span>
                                <span class="font-semibold">{{ stats.draft }}</span>
                            </div>
                            <div v-if="stats.failed > 0" class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-muted-foreground">
                                    <span class="inline-block size-2 rounded-full bg-red-500" /> Failed
                                </span>
                                <span class="font-semibold text-red-500">{{ stats.failed }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

