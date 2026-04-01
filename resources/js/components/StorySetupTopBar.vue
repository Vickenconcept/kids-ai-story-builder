<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps<{
    project: {
        uuid: string;
        title: string;
        status: string;
        can_start_media?: boolean;
        page_count: number;
        pages_completed: number;
        sharing_enabled: boolean;
        public_read_url: string;
    };
    storyCredits: number;
    viewMode: 'flip' | 'scroll';
    showViewMode?: boolean;
}>();

const emit = defineEmits<{
    'update:viewMode': [mode: 'flip' | 'scroll'];
}>();

const copiedPublic = ref(false);
const kdpTrim = ref<'8.5x8.5' | '8x10'>('8.5x8.5');
const kdpCompletedOnly = ref(false);
const kdpIncludePdf = ref(true);
const kdpExporting = ref(false);
const kdpExportError = ref<string | null>(null);
const kdpToast = ref<{ kind: 'info' | 'success' | 'error'; message: string } | null>(null);
let kdpToastTimer: ReturnType<typeof setTimeout> | null = null;

const kdpExportHref = computed(() => {
    const params = new URLSearchParams({
        trim: kdpTrim.value,
        completed_only: kdpCompletedOnly.value ? '1' : '0',
        include_pdf: kdpIncludePdf.value ? '1' : '0',
    });

    return `/stories/${props.project.uuid}/export/kdp?${params.toString()}`;
});

async function downloadKdpPackage(): Promise<void> {
    if (kdpExporting.value) {
        return;
    }

    kdpExportError.value = null;
    kdpExporting.value = true;
    showKdpToast('info', 'Preparing KDP export package...');

    try {
        const response = await fetch(kdpExportHref.value, {
            method: 'GET',
            headers: {
                Accept: 'application/zip',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Export failed');
        }

        const blob = await response.blob();
        showKdpToast('info', 'Export ready. Starting download...');
        const disposition = response.headers.get('content-disposition') ?? '';
        const match = disposition.match(/filename="?([^\";]+)"?/i);
        const filename = match?.[1] ?? `kdp-export-${props.project.uuid}.zip`;

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        showKdpToast('success', 'KDP ZIP download started.');
    } catch {
        kdpExportError.value = 'Export failed. Please try again.';
        showKdpToast('error', 'KDP export failed. Please try again.');
    } finally {
        kdpExporting.value = false;
    }
}

function showKdpToast(kind: 'info' | 'success' | 'error', message: string): void {
    kdpToast.value = { kind, message };

    if (kdpToastTimer) {
        clearTimeout(kdpToastTimer);
    }

    kdpToastTimer = setTimeout(() => {
        kdpToast.value = null;
        kdpToastTimer = null;
    }, 2800);
}

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
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0"
    >
        <div
            v-if="kdpToast"
            class="fixed top-4 right-4 z-[120] max-w-sm rounded-md border px-3 py-2 text-xs shadow-md backdrop-blur"
            :class="
                kdpToast.kind === 'success'
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                    : kdpToast.kind === 'error'
                      ? 'border-red-300 bg-red-50 text-red-900'
                      : 'border-sky-300 bg-sky-50 text-sky-900'
            "
        >
            {{ kdpToast.message }}
        </div>
    </Transition>

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
                        <span
                            v-if="project.can_start_media"
                            class="inline-flex items-center rounded-md border border-sky-300 bg-sky-50 px-2 py-0.5 font-semibold text-sky-800"
                        >
                            Stage: Draft review
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

                <Dialog v-if="project.status === 'ready'">
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm">Sell on Amazon</Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Publish This Story on Amazon KDP</DialogTitle>
                            <DialogDescription>
                                Yes, you can sell books made with this app. Follow this checklist to publish safely and avoid quality issues.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="space-y-4 text-sm">
                            <div class="rounded-md border border-border p-3">
                                <p class="font-semibold">Recommended flow</p>
                                <ol class="text-muted-foreground mt-2 list-inside list-decimal space-y-1.5">
                                    <li>Generate and review your story pages in this editor.</li>
                                    <li>Export to print/digital format (PDF for paperback, EPUB or DOCX for Kindle).</li>
                                    <li>Format for readability and print quality (consistent style, clear text, strong images).</li>
                                    <li>Upload to Amazon KDP with title, description, manuscript, and cover.</li>
                                    <li>Publish and track performance.</li>
                                </ol>
                            </div>

                            <div class="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-900">
                                <p class="font-semibold">Important quality rules</p>
                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    <li>Edit AI output before publishing. Do not upload raw drafts.</li>
                                    <li>Avoid repetitive, low-quality, or inconsistent content.</li>
                                    <li>Keep character consistency and image-story alignment.</li>
                                </ul>
                            </div>

                            <div class="rounded-md border border-sky-300 bg-sky-50 p-3 text-sky-900">
                                <p class="font-semibold">Positioning tip</p>
                                <p class="mt-1">
                                    Stronger message: "Create and sell your own children's books on Amazon."
                                </p>
                            </div>

                            <div class="rounded-md border border-border p-3">
                                <p class="font-semibold">Export settings</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="space-y-1">
                                        <span class="text-xs font-medium">Trim size</span>
                                        <select
                                            v-model="kdpTrim"
                                            class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                                        >
                                            <option value="8.5x8.5">8.5 x 8.5 in (square)</option>
                                            <option value="8x10">8 x 10 in</option>
                                        </select>
                                    </label>
                                    <label class="flex items-center gap-2 rounded-md border border-border p-2.5">
                                        <input v-model="kdpCompletedOnly" type="checkbox" class="size-4" />
                                        <span class="text-xs">Include only completed pages (text + image)</span>
                                    </label>
                                </div>

                                <label class="mt-3 flex items-center gap-2 rounded-md border border-border p-2.5">
                                    <input v-model="kdpIncludePdf" type="checkbox" class="size-4" />
                                    <span class="text-xs">Generate print-ready PDFs inside ZIP</span>
                                </label>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button size="sm" variant="secondary" :disabled="kdpExporting" @click="downloadKdpPackage">
                                    {{ kdpExporting ? 'Preparing export...' : 'Export for Amazon KDP (ZIP)' }}
                                </Button>
                                <a
                                    href="https://kdp.amazon.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-muted-foreground hover:text-foreground text-xs underline"
                                >
                                    Open Amazon KDP
                                </a>
                            </div>
                            <p v-if="kdpExportError" class="text-destructive text-xs">{{ kdpExportError }}</p>
                        </div>
                    </DialogContent>
                </Dialog>

                <div
                    v-if="showViewMode !== false"
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
