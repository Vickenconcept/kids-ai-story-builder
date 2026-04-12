<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Download, ExternalLink, Film, Play } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as videoLibraryIndex } from '@/routes/video-library';
import type { BreadcrumbItem } from '@/types';

type VideoAssetRow = {
    id: number;
    page_uuid: string;
    page_number: number;
    story_uuid: string;
    story_title: string;
    video_url: string;
    image_url: string | null;
    download_filename: string;
    story_editor_url: string;
};

const props = defineProps<{
    videos: VideoAssetRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Video library', href: videoLibraryIndex().url },
];

const preview = ref<VideoAssetRow | null>(null);

const previewOpen = computed({
    get: () => preview.value !== null,
    set: (open) => {
        if (!open) {
            preview.value = null;
        }
    },
});

function openPreview(row: VideoAssetRow) {
    preview.value = row;
}
</script>

<template>
    <Head title="Video library" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-violet-600 dark:text-violet-400">
                        <Film class="size-5" />
                        <span class="text-sm font-semibold uppercase tracking-wide">Pro & Elite</span>
                    </div>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Video library</h1>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Every page video you have generated for your flipbooks lives here. Preview or download MP4s to
                        post on YouTube, Shorts, TikTok, Reels, or other channels. If your browser blocks automatic
                        downloads from the host, open the link in a new tab and use Save as.
                    </p>
                </div>
                <Button as-child variant="outline" size="sm">
                    <Link href="/stories" class="inline-flex items-center gap-2">
                        <BookOpen class="size-4" />
                        Back to stories
                    </Link>
                </Button>
            </div>

            <div
                v-if="props.videos.length === 0"
                class="rounded-xl border border-dashed border-border bg-muted/30 px-6 py-12 text-center"
            >
                <Film class="text-muted-foreground mx-auto size-10 opacity-60" />
                <p class="mt-3 text-sm font-medium">No page videos yet</p>
                <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                    Turn on page video when you create or edit a story, finish your pages, then open the story editor to
                    generate videos. Completed clips will show up here automatically.
                </p>
                <Button as-child class="mt-5" size="sm">
                    <Link href="/stories/create">Create a story</Link>
                </Button>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card v-for="row in props.videos" :key="row.id" class="overflow-hidden pt-0">
                    <div class="bg-muted relative aspect-video w-full overflow-hidden">
                        <img
                            v-if="row.image_url"
                            :src="row.image_url"
                            :alt="`Illustration for ${row.story_title} page ${row.page_number}`"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="text-muted-foreground flex size-full items-center justify-center text-xs font-medium"
                        >
                            No cover image
                        </div>
                        <button
                            type="button"
                            class="absolute inset-0 flex items-center justify-center bg-black/35 opacity-0 transition-opacity hover:opacity-100 focus-visible:opacity-100"
                            @click="openPreview(row)"
                        >
                            <span
                                class="inline-flex size-12 items-center justify-center rounded-full bg-white/95 text-violet-700 shadow-md"
                            >
                                <Play class="size-6 translate-x-0.5" fill="currentColor" />
                            </span>
                            <span class="sr-only">Preview video</span>
                        </button>
                    </div>
                    <CardHeader class="pb-2">
                        <CardTitle class="line-clamp-2 text-base leading-snug">
                            {{ row.story_title }}
                        </CardTitle>
                        <p class="text-muted-foreground text-xs">Page {{ row.page_number }}</p>
                    </CardHeader>
                    <CardContent class="pb-2 pt-0">
                        <div class="flex flex-wrap gap-2">
                            <Button size="sm" variant="secondary" class="gap-1.5" @click="openPreview(row)">
                                <Play class="size-3.5" />
                                Preview
                            </Button>
                            <Button size="sm" variant="outline" class="gap-1.5" as-child>
                                <a
                                    :href="row.video_url"
                                    :download="row.download_filename"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Download class="size-3.5" />
                                    Download
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                    <CardFooter class="border-t pt-3">
                        <Button as-child variant="ghost" size="sm" class="h-auto gap-1 px-0 text-xs font-normal">
                            <Link :href="row.story_editor_url" class="inline-flex items-center gap-1">
                                Open in editor
                                <ExternalLink class="size-3" />
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="previewOpen">
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ preview?.story_title }} — page {{ preview?.page_number }}</DialogTitle>
                    <DialogDescription>Preview your generated page video.</DialogDescription>
                </DialogHeader>
                <div v-if="preview" class="overflow-hidden rounded-lg border bg-black">
                    <video :src="preview.video_url" class="max-h-[60vh] w-full" controls playsinline preload="metadata" />
                </div>
                <div v-if="preview" class="flex flex-wrap gap-2 pt-2">
                    <Button size="sm" variant="secondary" as-child>
                        <a
                            :href="preview.video_url"
                            :download="preview.download_filename"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5"
                        >
                            <Download class="size-3.5" />
                            Download MP4
                        </a>
                    </Button>
                    <Button size="sm" variant="outline" as-child>
                        <Link :href="preview.story_editor_url">Open story</Link>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
