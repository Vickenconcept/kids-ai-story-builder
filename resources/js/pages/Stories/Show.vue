<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Clapperboard } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import StoryCoverSettingsAccordion from '@/components/StoryCoverSettingsAccordion.vue';
import StoryFlipbook from '@/components/StoryFlipbook.vue';
import type {CoverConfigJson} from '@/components/StoryFlipbook.vue';
import StoryGenerationOverlay from '@/components/StoryGenerationOverlay.vue';
import StorySetupTopBar from '@/components/StorySetupTopBar.vue';
import { Button } from '@/components/ui/button';

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

type QuizDraftRow = {
    question: string;
    choices: string[];
    answer: string;
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
        illustration_style: string;
        tts_voice: string;
        flip_gameplay_enabled: boolean;
        cover_front: CoverConfigJson;
        cover_back: CoverConfigJson;
        sharing_enabled: boolean;
        public_read_url: string;
        flip_settings: Record<string, unknown> | null;
        queue: {
            total: number;
            pending: number;
            running: number;
            succeeded: number;
            failed: number;
            last_error: string | null;
        };
        can_start_media: boolean;
    };
    pages: PageRow[];
    story_credits: number;
    feature_tier: string;
    video_credit_cost: number;
}>();

const viewMode = ref<'flip' | 'scroll'>('flip');
const creditsOverlayDismissed = ref(false);
const generationSuccessTransition = ref(false);
const flipbookSectionRef = ref<HTMLElement | null>(null);
const generateImages = ref(true);
const generateAudio = ref(props.project.include_narration);
const generateVideo = ref(props.project.include_video);
const selectedIllustrationStyle = ref(props.project.illustration_style);
const selectedTtsVoice = ref(props.project.tts_voice || 'nova');
const pageDraftText = ref<Record<string, string>>({});
const pageDraftQuiz = ref<Record<string, QuizDraftRow[]>>({});
const pageSaveBusy = ref<Record<string, boolean>>({});
const pageDirtyText = ref<Record<string, boolean>>({});
const pageDirtyQuiz = ref<Record<string, boolean>>({});
const pageSaveState = ref<Record<string, 'idle' | 'unsaved' | 'saving' | 'saved' | 'error'>>({});
const advancedSaveBusy = ref(false);
const advancedDirty = ref(false);
const pageVideoBusy = ref<Record<string, boolean>>({});
const currentFlipPageUuid = ref<string | null>(null);

type ProgressSample = {
    at: number;
    pagesCompleted: number;
    queueSucceeded: number;
    queuePending: number;
    queueRunning: number;
};

const progressSamples = ref<ProgressSample[]>([]);

const queueState = computed(() =>
    props.project.queue ?? {
        total: 0,
        pending: 0,
        running: 0,
        succeeded: 0,
        failed: 0,
        last_error: null,
    },
);

const isGenerating = computed(() => props.project.status === 'processing');
const isDraftReviewStage = computed(() => props.project.can_start_media);
const unsavedPagesCount = computed(() => {
    const uuids = Object.keys(pageSaveState.value);

    return uuids.filter((uuid) => pageSaveState.value[uuid] === 'unsaved' || pageSaveState.value[uuid] === 'error').length;
});
const hasUnsavedPageChanges = computed(() => unsavedPagesCount.value > 0);
const isPro = computed(() => props.feature_tier === 'pro');
const canAffordSingleVideo = computed(() => props.story_credits >= props.video_credit_cost);
const canGeneratePageVideoInFlipbook = computed(() => isPro.value && canAffordSingleVideo.value);
const pageVideoActionHint = computed(() => {
    if (!isPro.value) {
        return 'Upgrade to Pro to generate page video.';
    }

    if (!canAffordSingleVideo.value) {
        return 'Not enough credits for a page video.';
    }

    return '';
});
const creditsExhausted = computed(() => {
    if (props.project.status !== 'failed') {
        return false;
    }

    const err = queueState.value.last_error ?? '';

    return /insufficient story credits/i.test(err) || /credits/i.test(err);
});

const showGenerationOverlay = computed(
    () =>
        isGenerating.value ||
        generationSuccessTransition.value ||
        (creditsExhausted.value && !creditsOverlayDismissed.value),
);

const etaSeconds = computed<number | null>(() => {
    if (!isGenerating.value) {
        return null;
    }

    if (progressSamples.value.length < 2) {
        return null;
    }

    const first = progressSamples.value[0];
    const last = progressSamples.value[progressSamples.value.length - 1];
    const elapsedSec = (last.at - first.at) / 1000;

    if (elapsedSec < 8) {
        return null;
    }

    const pagesDelta = last.pagesCompleted - first.pagesCompleted;
    const pageVelocity = pagesDelta > 0 ? pagesDelta / elapsedSec : 0;
    const remainingPages = Math.max(0, props.project.page_count - props.project.pages_completed);

    const queueDelta = last.queueSucceeded - first.queueSucceeded;
    const queueVelocity = queueDelta > 0 ? queueDelta / elapsedSec : 0;
    const remainingQueue = Math.max(0, queueState.value.pending + queueState.value.running);

    const estimates: number[] = [];

    if (pageVelocity > 0 && remainingPages > 0) {
        estimates.push(remainingPages / pageVelocity);
    }

    if (queueVelocity > 0 && remainingQueue > 0) {
        estimates.push(remainingQueue / queueVelocity);
    }

    if (estimates.length === 0) {
        return null;
    }

    return Math.max(5, Math.round(estimates.reduce((a, b) => a + b, 0) / estimates.length));
});

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

const illustrationStyleOptions = [
    { value: 'cartoon', label: 'Cartoon' },
    { value: 'watercolor', label: 'Watercolor' },
    { value: '3d', label: '3D render' },
    { value: 'storybook', label: 'Classic storybook' },
    { value: 'anime', label: 'Anime inspired' },
];

const ttsVoiceOptions = [
    { value: 'nova', label: 'Nova' },
    { value: 'alloy', label: 'Alloy' },
    { value: 'ash', label: 'Ash' },
    { value: 'coral', label: 'Coral' },
    { value: 'echo', label: 'Echo' },
    { value: 'fable', label: 'Fable' },
    { value: 'onyx', label: 'Onyx' },
    { value: 'sage', label: 'Sage' },
    { value: 'shimmer', label: 'Shimmer' },
];

function normalizeQuiz(raw: unknown): QuizDraftRow[] {
    let arr: unknown[] = [];

    if (Array.isArray(raw)) {
        arr = raw;
    } else if (typeof raw === 'string') {
        try {
            const parsed = JSON.parse(raw);

            if (Array.isArray(parsed)) {
                arr = parsed;
            }
        } catch {
            arr = [];
        }
    }

    return arr
        .map((item) => {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const o = item as Record<string, unknown>;

            return {
                question: String(o.question ?? '').trim(),
                choices: Array.isArray(o.choices) ? o.choices.map((c) => String(c)) : [],
                answer: String(o.answer ?? '').trim(),
            };
        })
        .filter((row): row is QuizDraftRow => Boolean(row && row.question));
}

function quizRowsFor(page: PageRow): QuizDraftRow[] {
    return normalizeQuiz(page.quiz_questions);
}

function emptyQuizRow(): QuizDraftRow {
    return { question: '', choices: ['', ''], answer: '' };
}

function addQuizQuestion(pageUuid: string): void {
    const next = [...(pageDraftQuiz.value[pageUuid] ?? [])];
    next.push(emptyQuizRow());
    pageDraftQuiz.value = { ...pageDraftQuiz.value, [pageUuid]: next };
    markPageQuizDirty(pageUuid);
}

function removeQuizQuestion(pageUuid: string, idx: number): void {
    const next = [...(pageDraftQuiz.value[pageUuid] ?? [])];
    next.splice(idx, 1);
    pageDraftQuiz.value = { ...pageDraftQuiz.value, [pageUuid]: next };
    markPageQuizDirty(pageUuid);
}

function addQuizChoice(pageUuid: string, qIdx: number): void {
    const rows = [...(pageDraftQuiz.value[pageUuid] ?? [])];
    const row = rows[qIdx];

    if (!row) {
        return;
    }

    row.choices = [...(row.choices ?? []), ''];
    rows[qIdx] = row;
    pageDraftQuiz.value = { ...pageDraftQuiz.value, [pageUuid]: rows };
    markPageQuizDirty(pageUuid);
}

function removeQuizChoice(pageUuid: string, qIdx: number, cIdx: number): void {
    const rows = [...(pageDraftQuiz.value[pageUuid] ?? [])];
    const row = rows[qIdx];

    if (!row) {
        return;
    }

    row.choices = (row.choices ?? []).filter((_, i) => i !== cIdx);
    rows[qIdx] = row;
    pageDraftQuiz.value = { ...pageDraftQuiz.value, [pageUuid]: rows };
    markPageQuizDirty(pageUuid);
}

function markPageTextDirty(pageUuid: string): void {
    pageDirtyText.value = { ...pageDirtyText.value, [pageUuid]: true };
    pageSaveState.value = { ...pageSaveState.value, [pageUuid]: 'unsaved' };
}

function markPageQuizDirty(pageUuid: string): void {
    pageDirtyQuiz.value = { ...pageDirtyQuiz.value, [pageUuid]: true };
    pageSaveState.value = { ...pageSaveState.value, [pageUuid]: 'unsaved' };
}

function saveLabelFor(pageUuid: string): string {
    const state = pageSaveState.value[pageUuid] ?? 'idle';

    if (state === 'saving') {
        return 'Saving...';
    }

    if (state === 'saved') {
        return 'Saved';
    }

    if (state === 'error') {
        return 'Save failed';
    }

    if (state === 'unsaved') {
        return 'Save changes';
    }

    return 'Save text';
}

function sanitizedQuiz(pageUuid: string): QuizDraftRow[] {
    const rows = pageDraftQuiz.value[pageUuid] ?? [];

    return rows
        .map((q) => ({
            question: q.question.trim(),
            choices: (q.choices ?? []).map((c) => c.trim()).filter((c) => c.length > 0),
            answer: q.answer.trim(),
        }))
        .filter((q) => q.question.length > 0 && q.answer.length > 0);
}

function pushProgressSample(): void {
    const now = Date.now();
    const next: ProgressSample = {
        at: now,
        pagesCompleted: props.project.pages_completed,
        queueSucceeded: queueState.value.succeeded,
        queuePending: queueState.value.pending,
        queueRunning: queueState.value.running,
    };
    const samples = [...progressSamples.value, next].filter((s) => now - s.at <= 90_000);
    progressSamples.value = samples.slice(-18);
}

async function startMediaGeneration(): Promise<void> {
    if (hasUnsavedPageChanges.value) {
        const confirmed = window.confirm(
            `You still have unsaved changes on ${unsavedPagesCount.value} page(s). Continue without saving those edits?`,
        );

        if (!confirmed) {
            return;
        }
    }

    const advancedSaved = await saveAdvancedSettings();

    if (!advancedSaved) {
        return;
    }

    router.post(
        `/stories/${props.project.uuid}/start-media`,
        {
            generate_images: generateImages.value,
            generate_audio: generateAudio.value,
            generate_video: generateVideo.value,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function savePageText(pageUuid: string): void {
    const page = props.pages.find((p) => p.uuid === pageUuid);

    if (!page) {
        return;
    }

    pageSaveBusy.value = { ...pageSaveBusy.value, [pageUuid]: true };
    pageSaveState.value = { ...pageSaveState.value, [pageUuid]: 'saving' };
    const quizPayload = sanitizedQuiz(pageUuid);
    router.patch(
        `/stories/${props.project.uuid}/pages/${pageUuid}`,
        {
            text_content: pageDraftText.value[pageUuid] ?? '',
            quiz_questions: props.project.include_quiz ? (quizPayload.length > 0 ? quizPayload : null) : null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                pageDirtyText.value = { ...pageDirtyText.value, [pageUuid]: false };
                pageDirtyQuiz.value = { ...pageDirtyQuiz.value, [pageUuid]: false };
                pageSaveState.value = { ...pageSaveState.value, [pageUuid]: 'saved' };
            },
            onError: () => {
                pageSaveState.value = { ...pageSaveState.value, [pageUuid]: 'error' };
            },
            onFinish: () => {
                pageSaveBusy.value = { ...pageSaveBusy.value, [pageUuid]: false };
            },
        },
    );
}

function canGenerateVideoForPage(page: PageRow): boolean {
    if (!isPro.value) {
        return false;
    }

    if (!canAffordSingleVideo.value) {
        return false;
    }

    return Boolean(page.image_url) && !Boolean(pageVideoBusy.value[page.uuid]);
}

function generateVideoForPage(page: PageRow): void {
    if (!canGenerateVideoForPage(page)) {
        return;
    }

    pageVideoBusy.value = { ...pageVideoBusy.value, [page.uuid]: true };
    router.post(
        `/stories/${props.project.uuid}/pages/${page.uuid}/generate-video`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                pageVideoBusy.value = { ...pageVideoBusy.value, [page.uuid]: false };
            },
        },
    );
}

function onFlipViewPageChange(pageUuid: string | null): void {
    if (pageUuid) {
        currentFlipPageUuid.value = pageUuid;
    }
}

function onFlipbookGeneratePageVideo(pageUuid: string): void {
    const page = props.pages.find((p) => p.uuid === pageUuid);

    if (!page) {
        return;
    }

    generateVideoForPage(page);
}

function saveAdvancedSettings(): Promise<boolean> {
    if (!advancedDirty.value) {
        return Promise.resolve(true);
    }

    return new Promise((resolve) => {
        advancedSaveBusy.value = true;
        router.patch(
            `/stories/${props.project.uuid}`,
            {
                illustration_style: selectedIllustrationStyle.value,
                meta: { tts_voice: selectedTtsVoice.value },
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    advancedDirty.value = false;
                    resolve(true);
                },
                onError: () => {
                    resolve(false);
                },
                onFinish: () => {
                    advancedSaveBusy.value = false;
                },
            },
        );
    });
}

watch(
    () => [props.project.pages_completed, queueState.value.succeeded, queueState.value.pending, queueState.value.running],
    () => {
        pushProgressSample();
    },
    { immediate: true },
);

watch(
    () => props.project.status,
    (next, prev) => {
        if (next === 'ready' && prev && prev !== 'ready') {
            generationSuccessTransition.value = true;
            window.setTimeout(async () => {
                generationSuccessTransition.value = false;
                await nextTick();
                flipbookSectionRef.value?.focus({ preventScroll: true });
                flipbookSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 1100);
        }

        if (next !== 'failed') {
            creditsOverlayDismissed.value = false;
        }

        if (next === 'ready' || next === 'failed') {
            progressSamples.value = [];
        }
    },
);

watch(
    () => props.project.can_start_media,
    (canStart) => {
        if (canStart) {
            generateImages.value = true;
            generateAudio.value = props.project.include_narration;
            generateVideo.value = props.project.include_video;
            selectedIllustrationStyle.value = props.project.illustration_style;
            selectedTtsVoice.value = props.project.tts_voice || 'nova';
            advancedDirty.value = false;
        }
    },
);

watch(
    () => props.pages,
    (pages) => {
        if (pages.length > 0 && !currentFlipPageUuid.value) {
            currentFlipPageUuid.value = pages[0].uuid;
        }

        pages.forEach((p) => {
            if (!pageDirtyText.value[p.uuid]) {
                pageDraftText.value = { ...pageDraftText.value, [p.uuid]: p.text_content ?? '' };
            }

            if (!pageDirtyQuiz.value[p.uuid]) {
                pageDraftQuiz.value = { ...pageDraftQuiz.value, [p.uuid]: quizRowsFor(p) };
            }

            if (!pageSaveState.value[p.uuid]) {
                pageSaveState.value = { ...pageSaveState.value, [p.uuid]: 'idle' };
            }
        });
    },
    { immediate: true, deep: true },
);

watch(selectedIllustrationStyle, () => {
    advancedDirty.value = true;
});

watch(selectedTtsVoice, () => {
    advancedDirty.value = true;
});

watch(generateVideo, (on) => {
    if (on) {
        generateImages.value = true;
    }
});

let poll: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    poll = setInterval(() => {
        if (props.project.status === 'processing') {
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
    <StoryGenerationOverlay
        :visible="showGenerationOverlay"
        :success-transition="generationSuccessTransition"
        :halted-by-credits="creditsExhausted"
        :status="project.status"
        :page-count="project.page_count"
        :pages-completed="project.pages_completed"
        :eta-seconds="etaSeconds"
        :queue="queueState"
        @close="creditsOverlayDismissed = true"
    />

    <div class="bg-background min-h-screen w-full">
        <StorySetupTopBar
            :project="project"
            :story-credits="props.story_credits"
            :view-mode="viewMode"
            :show-view-mode="!isDraftReviewStage"
            @update:view-mode="viewMode = $event"
        />

        <div class="mx-auto w-full max-w-440 space-y-4 px-4 py-3 sm:px-6">
            <div
                v-if="!isDraftReviewStage"
                class="bg-muted/60 flex rounded-lg border border-border p-0.5 text-xs font-medium sm:hidden"
                role="group"
                aria-label="View mode"
            >
                <button
                    type="button"
                    class="flex-1 rounded-md px-3 py-1.5 transition-colors"
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
                    class="flex-1 rounded-md px-3 py-1.5 transition-colors"
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

            <div
                v-if="project.status === 'failed'"
                class="rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-2.5 text-sm"
            >
                Story text generation failed. Credits for completed steps were charged idempotently.
                Create a new project to retry.
            </div>

            <section v-if="isDraftReviewStage" class="grid lg:grid-cols-5 gap-4 ">
                 <div class="space-y-4 lg:col-span-3 col-span-1">
                    <article
                        v-for="page in pages"
                        :key="page.uuid"
                        class="rounded-xl border border-border bg-card/50 p-4"
                    >
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h2 class="text-base font-semibold">Page {{ page.page_number }}</h2>
                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="!canGenerateVideoForPage(page)"
                                    @click="generateVideoForPage(page)"
                                >
                                    <Clapperboard class="mr-1 size-4" />
                                    {{ page.video_url ? 'Regenerate video' : 'Generate video' }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="Boolean(pageSaveBusy[page.uuid])"
                                    @click="savePageText(page.uuid)"
                                >
                                    {{ saveLabelFor(page.uuid) }}
                                </Button>
                            </div>
                        </div>
                        <p class="mb-2 text-xs text-muted-foreground">
                            Per-page video costs {{ props.video_credit_cost }} credits.
                        </p>
                        <p v-if="!isPro" class="mb-2 text-xs text-amber-600">
                            Upgrade to Pro to generate page video.
                        </p>
                        <p v-else-if="!canAffordSingleVideo" class="mb-2 text-xs text-destructive">
                            Not enough credits for a page video.
                        </p>
                        <p
                            v-if="pageSaveState[page.uuid] === 'saved'"
                            class="mb-2 text-xs text-emerald-600"
                        >
                            Saved
                        </p>
                        <p
                            v-else-if="pageSaveState[page.uuid] === 'error'"
                            class="mb-2 text-xs text-destructive"
                        >
                            Save failed. Please try again.
                        </p>
                        <textarea
                            v-model="pageDraftText[page.uuid]"
                            @input="markPageTextDirty(page.uuid)"
                            rows="6"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm leading-relaxed"
                        />

                        <div v-if="project.include_quiz" class="mt-3 rounded-lg border border-border/60 bg-background/70 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold tracking-wide uppercase">Quiz (editable)</p>
                                <Button type="button" size="sm" variant="outline" @click="addQuizQuestion(page.uuid)">
                                    Add question
                                </Button>
                            </div>
                            <div v-if="(pageDraftQuiz[page.uuid] ?? []).length > 0" class="mt-2 space-y-2.5">
                                <div
                                    v-for="(q, idx) in pageDraftQuiz[page.uuid]"
                                    :key="`${page.uuid}-q-${idx}`"
                                    class="rounded-md border border-border/60 p-2.5"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <p class="text-xs font-semibold">Question {{ idx + 1 }}</p>
                                        <button
                                            type="button"
                                            class="text-destructive text-xs"
                                            @click="removeQuizQuestion(page.uuid, idx)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                    <input
                                        v-model="q.question"
                                        @input="markPageQuizDirty(page.uuid)"
                                        type="text"
                                        placeholder="Question"
                                        class="border-input bg-background mb-2 w-full rounded-md border px-2 py-1.5 text-sm"
                                    />
                                    <div class="space-y-1.5">
                                        <div
                                            v-for="(choice, cIdx) in q.choices"
                                            :key="`${page.uuid}-q-${idx}-c-${cIdx}`"
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                v-model="q.choices[cIdx]"
                                                @input="markPageQuizDirty(page.uuid)"
                                                type="text"
                                                :placeholder="`Choice ${cIdx + 1}`"
                                                class="border-input bg-background w-full rounded-md border px-2 py-1.5 text-sm"
                                            />
                                            <button
                                                type="button"
                                                class="text-muted-foreground text-xs"
                                                @click="removeQuizChoice(page.uuid, idx, cIdx)"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <button
                                            type="button"
                                            class="text-xs font-medium"
                                            @click="addQuizChoice(page.uuid, idx)"
                                        >
                                            + Add choice
                                        </button>
                                    </div>
                                    <input
                                        v-model="q.answer"
                                        @input="markPageQuizDirty(page.uuid)"
                                        type="text"
                                        placeholder="Correct answer"
                                        class="border-input bg-background mt-2 w-full rounded-md border px-2 py-1.5 text-sm"
                                    />
                                </div>
                            </div>
                            <p v-else class="text-muted-foreground mt-2 text-xs">No quiz questions yet. Add one if needed.</p>
                        </div>
                    </article>
                </div>

                <aside class="lg:col-span-2 border-border bg-card/70 top-20 h-fit rounded-xl border p-4 shadow-sm lg:sticky">
                    <p class="text-sm font-semibold tracking-tight">Text review step</p>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Edit your generated pages below, then continue to media generation.
                    </p>
                    <p class="text-muted-foreground mt-2 text-xs">
                        Your create-page settings still apply. Use Advanced options if you want to adjust them.
                    </p>

                    <div class="mt-4 grid gap-2">
                        <label class="hover:bg-muted/40 flex cursor-pointer items-center justify-between rounded-lg border border-border/60 p-2.5 transition-colors">
                            <span class="text-sm">Generate illustrations</span>
                            <span class="relative inline-flex">
                                <input v-model="generateImages" :disabled="generateVideo" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-primary/80 peer-disabled:opacity-60 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>
                        <label class="hover:bg-muted/40 flex cursor-pointer items-center justify-between rounded-lg border border-border/60 p-2.5 transition-colors">
                            <span class="text-sm">Generate narration (TTS)</span>
                            <span class="relative inline-flex">
                                <input v-model="generateAudio" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>
                        <label
                            class="hover:bg-muted/40 flex cursor-pointer items-center justify-between rounded-lg border border-border/60 p-2.5 transition-colors"
                            :class="{ 'opacity-60': !project.include_video }"
                        >
                            <span class="text-sm">Generate page video (Pro)</span>
                            <span class="relative inline-flex">
                                <input v-model="generateVideo" :disabled="!project.include_video" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-primary/80 peer-disabled:opacity-60 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>
                    </div>

                    <Button type="button" class="mt-4 w-full" @click="startMediaGeneration">
                        {{ generateImages || generateAudio || generateVideo ? 'Continue: generate media' : 'Finish as text-only' }}
                    </Button>
                    <p v-if="hasUnsavedPageChanges" class="mt-2 text-xs text-amber-600">
                        You have unsaved edits on {{ unsavedPagesCount }} page(s). Save them before continuing.
                    </p>

                    <details class="border-border bg-background/60 mt-4 group rounded-lg border">
                        <summary class="hover:bg-muted/50 flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-sm font-medium">
                            <span>Advanced options</span>
                            <span class="text-muted-foreground text-xs transition-transform group-open:rotate-180">▼</span>
                        </summary>
                        <div class="border-border space-y-3 border-t p-3">
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">Illustration style</label>
                                <select v-model="selectedIllustrationStyle" class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm">
                                    <option v-for="opt in illustrationStyleOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">Narration voice</label>
                                <select v-model="selectedTtsVoice" class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm">
                                    <option v-for="opt in ttsVoiceOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            <Button type="button" size="sm" variant="outline" class="w-full" :disabled="advancedSaveBusy" @click="saveAdvancedSettings">
                                {{ advancedSaveBusy ? 'Saving...' : advancedDirty ? 'Save advanced settings' : 'Advanced settings saved' }}
                            </Button>
                        </div>
                    </details>
                </aside>
            </section>

            <section class="min-w-0 space-y-4">
                <div
                    v-if="!isDraftReviewStage && viewMode === 'flip' && pages.length > 0"
                    ref="flipbookSectionRef"
                    tabindex="-1"
                    class="border-border bg-card/30 relative w-full rounded-xl border p-3 shadow-sm"
                >
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
                        :show-page-video-action="true"
                        :can-generate-page-video="canGeneratePageVideoInFlipbook"
                        :page-video-busy="pageVideoBusy"
                        :page-video-action-hint="pageVideoActionHint"
                        @view-page-change="onFlipViewPageChange"
                        @generate-page-video="onFlipbookGeneratePageVideo"
                    >
                        <template #setup-extra>
                            <StoryCoverSettingsAccordion
                                :story-uuid="project.uuid"
                                :title="project.title"
                                :topic="project.topic"
                                :cover-front="project.cover_front"
                                :cover-back="project.cover_back"
                            />
                        </template>
                    </StoryFlipbook>
                </div>

            <p
                v-else-if="!isDraftReviewStage && viewMode === 'flip' && pages.length === 0"
                class="text-muted-foreground rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm"
            >
                Pages are not ready yet. Switch to Scroll or wait — the flip book appears once story text exists.
            </p>

            <div v-if="!isDraftReviewStage && viewMode === 'scroll'" class="flex flex-col gap-8">
                <p v-if="pages.length === 0" class="text-muted-foreground text-sm">
                    No pages yet — generation may still be running. This view will fill in when text and assets are
                    ready.
                </p>
                <article
                    v-for="page in pages"
                    :key="page.uuid"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-lg font-medium">Page {{ page.page_number }}</h2>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="!canGenerateVideoForPage(page)"
                            @click="generateVideoForPage(page)"
                        >
                            <Clapperboard class="mr-1 size-4" />
                            {{ page.video_url ? 'Regenerate video' : 'Generate video' }}
                        </Button>
                    </div>
                    <p class="mb-3 text-xs text-muted-foreground">Per-page video costs {{ props.video_credit_cost }} credits.</p>

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
            </section>
        </div>
    </div>
</template>
