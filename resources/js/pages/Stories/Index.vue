<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Plus } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type ProjectRow = {
    id: number;
    uuid: string;
    title: string;
    topic: string;
    status: string;
    page_count: number;
    pages_completed: number;
    created_at: string;
    updated_at: string;
};

defineProps<{
    projects: ProjectRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stories', href: '/stories' },
];
</script>

<template>
    <Head title="Stories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Story projects</h1>
                    <p class="text-muted-foreground text-sm">
                        URLs use UUIDs. With default settings, all generation jobs run on the
                        <code class="text-xs">default</code> queue (run
                        <code class="text-xs">php artisan queue:work</code>).
                    </p>
                </div>
                <Button as-child>
                    <Link href="/stories/create" class="flex items-center gap-2">
                        <Plus class="size-4" />
                        New story
                    </Link>
                </Button>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <div
                    class="text-muted-foreground grid grid-cols-[1fr_auto_auto_auto] gap-2 border-b px-4 py-2 text-xs font-medium uppercase"
                >
                    <span>Title</span>
                    <span>Status</span>
                    <span>Progress</span>
                    <span class="text-right">Open</span>
                </div>
                <div
                    v-if="projects.length === 0"
                    class="text-muted-foreground py-10 text-center text-sm"
                >
                    No stories yet. Create one to start the AI pipeline.
                </div>
                <div
                    v-for="p in projects"
                    :key="p.uuid"
                    class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-2 border-b px-4 py-3 text-sm last:border-0"
                >
                    <span class="font-medium">{{ p.title }}</span>
                    <span class="capitalize">{{ p.status }}</span>
                    <span>{{ p.pages_completed }} / {{ p.page_count }} pages</span>
                    <div class="text-right">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/stories/${p.uuid}`" class="inline-flex items-center gap-1">
                                <BookOpen class="size-3.5" />
                                View
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
