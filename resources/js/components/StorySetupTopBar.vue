<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Facebook, Linkedin } from 'lucide-vue-next';
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
    featureTier: string;
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

const isElite = computed(() => props.featureTier === 'elite');

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

const shareMessage = computed(() => {
    const title = props.project.title.trim();

    return title ? `Read "${title}"` : 'Read this story';
});

const socialShareLinks = computed(() => {
    const url = props.project.public_read_url;
    const msg = shareMessage.value;
    const u = encodeURIComponent(url);
    const text = encodeURIComponent(msg);
    const textWithLink = encodeURIComponent(`${msg}\n${url}`);

    return [
        {
            id: 'facebook',
            label: 'Facebook',
            href: `https://www.facebook.com/sharer/sharer.php?u=${u}`,
            class: 'text-[#1877F2]',
        },
        {
            id: 'whatsapp',
            label: 'WhatsApp',
            href: `https://wa.me/?text=${textWithLink}`,
            class: 'text-[#25D366]',
        },
        {
            id: 'x',
            label: 'X',
            href: `https://twitter.com/intent/tweet?url=${u}&text=${text}`,
            class: 'text-foreground',
        },
        {
            id: 'linkedin',
            label: 'LinkedIn',
            href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}`,
            class: 'text-[#0A66C2]',
        },
        {
            id: 'telegram',
            label: 'Telegram',
            href: `https://t.me/share/url?url=${u}&text=${text}`,
            class: 'text-[#26A5E4]',
        },
        {
            id: 'reddit',
            label: 'Reddit',
            href: `https://www.reddit.com/submit?url=${u}&title=${text}`,
            class: 'text-[#FF4500]',
        },
    ];
});
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

                            <div class="border-border space-y-2 border-t pt-3">
                                <p class="text-muted-foreground text-xs font-medium">Share on social</p>
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                    <a
                                        v-for="item in socialShareLinks"
                                        :key="item.id"
                                        :href="item.href"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="border-border hover:bg-muted/60 flex flex-col items-center gap-1 rounded-lg border p-2 text-center transition-colors"
                                        :aria-label="`Share on ${item.label}`"
                                    >
                                        <span class="flex size-8 items-center justify-center" :class="item.class">
                                            <Facebook v-if="item.id === 'facebook'" class="size-6" aria-hidden="true" />
                                            <Linkedin v-else-if="item.id === 'linkedin'" class="size-6" aria-hidden="true" />
                                            <svg
                                                v-else-if="item.id === 'whatsapp'"
                                                class="size-6"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.688 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                                />
                                            </svg>
                                            <svg
                                                v-else-if="item.id === 'x'"
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                                                />
                                            </svg>
                                            <svg
                                                v-else-if="item.id === 'telegram'"
                                                class="size-6"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
                                                />
                                            </svg>
                                            <svg
                                                v-else-if="item.id === 'reddit'"
                                                class="size-6"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-1.093-.435 1.52c-.735.2-1.405.53-1.996.98a.75.75 0 0 0-.056 1.14l.82.82a.75.75 0 0 0 1.06 0l.5-.5a.75.75 0 0 1 1.06 0l.5.5a.75.75 0 0 0 1.06 0l.82-.82a.75.75 0 0 0-.056-1.14 6.5 6.5 0 0 0-1.996-.98l-.435-1.52-2.597 1.093a1.25 1.25 0 0 1-1.248-1.25c0-.688.562-1.25 1.25-1.25a1.25 1.25 0 0 1 1.06.62l.85-.5a1.25 1.25 0 0 1 .19-.11zM12 8.5c-2.61 0-4.8 1.23-5.8 3.1-.25.5-.4 1.06-.4 1.65 0 2.76 2.76 5 6.2 5s6.2-2.24 6.2-5c0-.59-.15-1.15-.4-1.65-1-1.87-3.19-3.1-5.8-3.1z"
                                                />
                                            </svg>
                                        </span>
                                        <span class="text-muted-foreground text-[10px] font-medium leading-tight">{{ item.label }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>

                <Dialog v-if="project.status === 'ready' && isElite">
                    <DialogTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="border-2 border-amber-500 bg-amber-50 font-semibold text-amber-900 hover:bg-amber-100"
                        >
                            Sell on Amazon
                        </Button>
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
