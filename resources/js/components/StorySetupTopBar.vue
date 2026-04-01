<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    project: {
        uuid: string;
        title: string;
        status: string;
        page_count: number;
        pages_completed: number;
        sharing_enabled: boolean;
        public_read_url: string;
    };
    storyCredits: number;
    viewMode: 'flip' | 'scroll';
}>();

const emit = defineEmits<{
    'update:viewMode': [mode: 'flip' | 'scroll'];
}>();

const copiedPublic = ref(false);

function toggleSharing(e: Event): void {
    router.patch(
        `/stories/${props.project.uuid}`,
        { sharing_enabled: (e.target as HTMLInputElement).checked },
        { preserveScroll: true },
    );
}

async function copyPublicLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.project.public_read_url);
        copiedPublic.value = true;
        window.setTimeout(() => {
            copiedPublic.value = false;
        }, 2000);
    } catch {
        /* ignore */
    }
}
</script>

<template>
    <div class="border-border/60 sticky top-0 z-50 border-b bg-background/95 backdrop-blur">
        <div class="mx-auto flex w-full max-w-440 items-center justify-between gap-3 px-4 py-2.5 sm:px-6">
            <div class="flex min-w-0 items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link href="/stories">Back</Link>
                </Button>
                <div class="min-w-0">
                    <h1 class="truncate text-base font-semibold tracking-tight sm:text-lg">{{ project.title }}</h1>
                    <div class="mt-1 hidden items-center gap-2 text-xs md:flex">
                        <span class="border-border bg-muted/50 inline-flex items-center rounded-md border px-2 py-0.5 font-medium capitalize">
                            Status: {{ project.status }}
                        </span>
                        <span class="border-border bg-muted/50 inline-flex items-center rounded-md border px-2 py-0.5 font-medium">
                            Pages: {{ project.pages_completed }} / {{ project.page_count }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 font-bold text-amber-800">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
                                <path d="M8.8 12h6.4M12 8.8v6.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            Credits: {{ storyCredits }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Dialog v-if="project.status === 'ready'">
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm">Share</Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Public reader link</DialogTitle>
                            <DialogDescription>
                                Share this read-only story link and control whether public access is enabled.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-3">
                            <label class="flex cursor-pointer items-start justify-between gap-3 rounded-md border border-border p-3">
                                <span>
                                    <span class="font-medium">Enable public access</span>
                                    <span class="text-muted-foreground block text-xs">When off, guests see "page not found".</span>
                                </span>
                                <span class="relative mt-0.5 inline-flex">
                                    <input
                                        :checked="project.sharing_enabled"
                                        type="checkbox"
                                        class="peer sr-only"
                                        @change="toggleSharing"
                                    />
                                    <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                        <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                                    </span>
                                </span>
                            </label>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <input
                                    :value="project.public_read_url"
                                    type="text"
                                    readonly
                                    class="border-input bg-muted/40 text-muted-foreground min-w-0 flex-1 rounded-md border px-2.5 py-2 text-xs"
                                />
                                <Button type="button" size="sm" variant="secondary" class="shrink-0" @click="copyPublicLink">
                                    {{ copiedPublic ? 'Copied' : 'Copy link' }}
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>

                <div
                    class="bg-muted/60 hidden rounded-lg border border-border p-0.5 text-xs font-medium sm:flex"
                    role="group"
                    aria-label="View mode"
                >
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 transition-colors"
                        :class="
                            viewMode === 'flip'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="emit('update:viewMode', 'flip')"
                    >
                        Flip book
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 transition-colors"
                        :class="
                            viewMode === 'scroll'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="emit('update:viewMode', 'scroll')"
                    >
                        Scroll
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
