<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import StoryFlipbook, { type CoverConfigJson } from '@/components/StoryFlipbook.vue';
import { COVER_FRAME_OPTIONS, type CoverFrameId, normalizeCoverFrame } from '@/lib/coverFrames';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type PageRow = {
    id: number;
    uuid: string;
    page_number: number;
    text_content: string | null;
    quiz_questions: unknown;
    asset_errors: Record<string, string> | null;
    image_url: string | null;
    audio_url: string | null;
    video_url: string | null;
};

const props = defineProps<{
    project: {
        id: number;
        uuid: string;
        title: string;
        topic: string;
        status: string;
        page_count: number;
        pages_completed: number;
        include_quiz: boolean;
        include_narration: boolean;
        include_video: boolean;
        flip_gameplay_enabled: boolean;
        cover_front: CoverConfigJson;
        cover_back: CoverConfigJson;
        sharing_enabled: boolean;
        public_read_url: string;
        flip_settings: Record<string, unknown> | null;
    };
    pages: PageRow[];
}>();

const viewMode = ref<'flip' | 'scroll'>('flip');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stories', href: '/stories' },
    { title: props.project.title, href: `/stories/${props.project.uuid}` },
];

const flipbookKey = computed(() =>
    [
        props.project.uuid,
        props.project.status,
        props.project.pages_completed,
        props.project.flip_gameplay_enabled,
        JSON.stringify(props.project.cover_front),
        JSON.stringify(props.project.cover_back),
        JSON.stringify(props.project.flip_settings),
        props.project.sharing_enabled,
        props.pages.length,
        props.pages.map((p) => p.uuid + (p.quiz_questions ? JSON.stringify(p.quiz_questions) : '')).join('-'),
    ].join('|'),
);

const coverSurface = ref<'front' | 'back'>('front');
const solidColor = ref('#4f46e5');
const gradFrom = ref('#6366f1');
const gradTo = ref('#ec4899');
const gradAngle = ref(135);
const coverAiBusy = ref(false);
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

function patchCoverConfig(config: Record<string, string | number | boolean | null | undefined>): void {
    const payload =
        coverSurface.value === 'front' ? { cover_front: config } : { cover_back: config };
    router.patch(`/stories/${props.project.uuid}`, payload, { preserveScroll: true });
}

const activeSurfaceCover = computed(() =>
    coverSurface.value === 'front' ? props.project.cover_front : props.project.cover_back,
);

const selectedCoverFrame = computed(() => normalizeCoverFrame(activeSurfaceCover.value?.frame));

function applyCoverFrame(frameId: CoverFrameId): void {
    const raw = activeSurfaceCover.value;
    const base =
        raw && typeof raw === 'object' && raw !== null && 'kind' in raw
            ? { ...(raw as Record<string, string | number | boolean | null | undefined>) }
            : ({ kind: 'solid', color: solidColor.value } as const);
    patchCoverConfig({ ...base, frame: frameId });
}

function applySolidCover(): void {
    patchCoverConfig({
        kind: 'solid',
        color: solidColor.value,
        frame: selectedCoverFrame.value,
    });
}

function applyGradientCover(): void {
    patchCoverConfig({
        kind: 'gradient',
        angle: gradAngle.value,
        from: gradFrom.value,
        to: gradTo.value,
        frame: selectedCoverFrame.value,
    });
}

function onCoverFilePick(e: Event): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) {
        return;
    }
    const fd = new FormData();
    fd.append('surface', coverSurface.value);
    fd.append('file', file);
    router.post(`/stories/${props.project.uuid}/cover-upload`, fd, { preserveScroll: true });
}

function generateAiCover(): void {
    coverAiBusy.value = true;
    router.post(
        `/stories/${props.project.uuid}/cover-ai`,
        { surface: coverSurface.value },
        {
            preserveScroll: true,
            onFinish: () => {
                coverAiBusy.value = false;
            },
        },
    );
}

let poll: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    poll = setInterval(() => {
        if (props.project.status !== 'ready' && props.project.status !== 'failed') {
            router.reload({ only: ['project', 'pages'] });
        }
    }, 5000);
});

onUnmounted(() => {
    if (poll) {
        clearInterval(poll);
    }
});
</script>

<template>
    <Head :title="project.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ project.title }}</h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ project.topic }}</p>
                    <p class="mt-2 text-sm capitalize">
                        <span class="font-medium">Status:</span> {{ project.status }}
                        ·
                        <span class="font-medium">Pages:</span>
                        {{ project.pages_completed }} / {{ project.page_count }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div
                        class="bg-muted/60 flex rounded-lg border border-border p-0.5 text-xs font-medium"
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
                            @click="viewMode = 'flip'"
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
                            @click="viewMode = 'scroll'"
                        >
                            Scroll
                        </button>
                    </div>
                    <Button variant="outline" as-child>
                        <Link href="/stories">Back to list</Link>
                    </Button>
                </div>
            </div>

            <div
                v-if="project.status === 'failed'"
                class="rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm"
            >
                Story text generation failed. Credits for completed steps were charged idempotently.
                Create a new project to retry.
            </div>

            <div
                v-if="project.status === 'ready'"
                class="border-border bg-card/80 w-full max-w-3xl rounded-xl border p-4 text-sm shadow-sm backdrop-blur-sm"
            >
                <p class="font-medium">Public reader link</p>
                <p class="text-muted-foreground mt-1 text-xs">
                    Anyone with the link can open a read-only flip book (same covers, narration, and quiz pages you
                    configured). You can turn this off anytime.
                </p>
                <label class="mt-3 flex cursor-pointer items-start gap-2">
                    <input
                        :checked="project.sharing_enabled"
                        type="checkbox"
                        class="border-input mt-1 size-4 rounded"
                        @change="toggleSharing"
                    />
                    <span>
                        <span class="font-medium">Enable public access</span>
                        <span class="text-muted-foreground block text-xs">
                            When off, the link below returns “page not found” for guests.
                        </span>
                    </span>
                </label>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        :value="project.public_read_url"
                        type="text"
                        readonly
                        class="border-input bg-muted/40 text-muted-foreground min-w-0 flex-1 rounded-md border px-3 py-2 text-xs"
                    />
                    <Button type="button" size="sm" variant="secondary" class="shrink-0" @click="copyPublicLink">
                        {{ copiedPublic ? 'Copied' : 'Copy link' }}
                    </Button>
                </div>
            </div>

            <details
                class="border-border bg-card/80 w-full max-w-3xl rounded-xl border text-sm shadow-sm backdrop-blur-sm"
            >
                <summary
                    class="hover:bg-muted/40 flex cursor-pointer list-none items-center gap-2 rounded-xl px-4 py-3 font-medium select-none"
                >
                    Book covers (front &amp; back)
                </summary>
                <div class="border-border space-y-4 border-t px-4 py-4">
                    <p class="text-muted-foreground text-xs">
                        Solid color, linear gradient, image upload (JPEG, PNG, WebP, or animated GIF), or AI-generated
                        art. Visitors who use your public reader link see the same covers and flip layout.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            :variant="coverSurface === 'front' ? 'default' : 'outline'"
                            @click="coverSurface = 'front'"
                        >
                            Front cover
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="coverSurface === 'back' ? 'default' : 'outline'"
                            @click="coverSurface = 'back'"
                        >
                            Back cover
                        </Button>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs">Cover frame style</Label>
                        <p class="text-muted-foreground text-xs">
                            Border and embossing on the hard cover. Front and back can each use a different template.
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="opt in COVER_FRAME_OPTIONS"
                                :key="opt.id"
                                type="button"
                                class="rounded-lg border p-3 text-left transition-colors select-none"
                                :class="
                                    selectedCoverFrame === opt.id
                                        ? 'border-primary bg-primary/10 ring-primary/25 ring-1'
                                        : 'border-border hover:border-muted-foreground/40'
                                "
                                @click="applyCoverFrame(opt.id)"
                            >
                                <span class="text-sm font-medium">{{ opt.label }}</span>
                                <span class="text-muted-foreground mt-1 block text-xs leading-snug">{{ opt.hint }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label class="text-xs">Solid color</Label>
                            <div class="flex flex-wrap items-center gap-2">
                                <input v-model="solidColor" type="color" class="h-9 w-14 cursor-pointer rounded border" />
                                <Button type="button" size="sm" variant="secondary" @click="applySolidCover">
                                    Apply
                                </Button>
                            </div>
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label class="text-xs">Linear gradient</Label>
                            <div class="flex flex-wrap items-end gap-2">
                                <label class="text-xs">
                                    From
                                    <input v-model="gradFrom" type="color" class="mt-1 block h-9 w-14 rounded border" />
                                </label>
                                <label class="text-xs">
                                    To
                                    <input v-model="gradTo" type="color" class="mt-1 block h-9 w-14 rounded border" />
                                </label>
                                <label class="text-xs">
                                    Angle
                                    <input
                                        v-model.number="gradAngle"
                                        type="number"
                                        min="0"
                                        max="360"
                                        class="border-input bg-background mt-1 block w-20 rounded-md border px-2 py-1"
                                    />
                                </label>
                                <Button type="button" size="sm" variant="secondary" @click="applyGradientCover">
                                    Apply gradient
                                </Button>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-xs">Upload image or GIF</Label>
                            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="onCoverFilePick" />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-xs">AI cover (uses your story title &amp; topic)</Label>
                            <Button type="button" size="sm" :disabled="coverAiBusy" @click="generateAiCover">
                                {{ coverAiBusy ? 'Generating…' : 'Generate with AI' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </details>

            <div v-if="viewMode === 'flip' && pages.length > 0" class="w-full">
                <StoryFlipbook
                    :key="flipbookKey"
                    :title="project.title"
                    :pages="pages"
                    :play-audio-on-flip="project.include_narration"
                    :story-uuid="project.uuid"
                    :include-quiz="project.include_quiz"
                    :gameplay-enabled="project.flip_gameplay_enabled"
                    :setup-mode="true"
                    :cover-front="project.cover_front"
                    :cover-back="project.cover_back"
                    :flip-settings="project.flip_settings"
                />
            </div>
            <p
                v-else-if="viewMode === 'flip' && pages.length === 0"
                class="text-muted-foreground rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm"
            >
                Pages are not ready yet. Switch to Scroll or wait — the flip book appears once story text exists.
            </p>

            <div v-if="viewMode === 'scroll'" class="flex flex-col gap-8">
                <p v-if="pages.length === 0" class="text-muted-foreground text-sm">
                    No pages yet — generation may still be running. This view will fill in when text and assets are
                    ready.
                </p>
                <article
                    v-for="page in pages"
                    :key="page.uuid"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-3 text-lg font-medium">Page {{ page.page_number }}</h2>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3 text-sm leading-relaxed">
                            <p>{{ page.text_content }}</p>
                            <div
                                v-if="project.include_quiz && page.quiz_questions"
                                class="bg-muted/40 rounded-lg p-3 text-xs"
                            >
                                <p class="font-medium">Quiz</p>
                                <pre class="mt-1 overflow-x-auto whitespace-pre-wrap">{{
                                    JSON.stringify(page.quiz_questions, null, 2)
                                }}</pre>
                            </div>
                            <div
                                v-if="page.asset_errors && Object.keys(page.asset_errors).length"
                                class="text-destructive text-xs"
                            >
                                <p class="font-medium">Asset errors</p>
                                <ul class="list-inside list-disc">
                                    <li v-for="(msg, key) in page.asset_errors" :key="key">
                                        {{ key }}: {{ msg }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3">
                            <div v-if="page.image_url" class="overflow-hidden rounded-lg border">
                                <img
                                    :src="page.image_url"
                                    :alt="`Illustration page ${page.page_number}`"
                                    draggable="false"
                                    class="max-h-80 w-full select-none object-contain [-webkit-user-drag:none]"
                                />
                            </div>
                            <div v-else class="text-muted-foreground text-sm">Image pending…</div>
                            <audio v-if="page.audio_url" :src="page.audio_url" controls class="w-full" />
                            <p v-else-if="project.include_narration" class="text-muted-foreground text-sm">
                                Audio pending…
                            </p>
                            <video
                                v-if="page.video_url"
                                :src="page.video_url"
                                controls
                                class="w-full max-w-md rounded-lg"
                            />
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
