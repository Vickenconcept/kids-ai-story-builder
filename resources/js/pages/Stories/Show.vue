<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Clapperboard, Loader2, Volume2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import StoryCoverSettingsAccordion from '@/components/StoryCoverSettingsAccordion.vue';
import StoryFlipbook from '@/components/StoryFlipbook.vue';
import type {CoverConfigJson} from '@/components/StoryFlipbook.vue';
import StoryQuizSheet from '@/components/StoryQuizSheet.vue';
import StoryGenerationOverlay from '@/components/StoryGenerationOverlay.vue';
import StorySetupTopBar from '@/components/StorySetupTopBar.vue';
import { useCreditsModal } from '@/composables/useCreditsModal';
import { Button } from '@/components/ui/button';
import { normalizeStoryQuizQuestions, type StoryQuizQuestion } from '@/lib/storyQuiz';
import { videoPlaybackSrc } from '@/lib/videoPlaybackUrl';

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
    video_generating?: boolean;
    audio_generating?: boolean;
};

type QuizDraftRow = StoryQuizQuestion;

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
    audio_credit_cost: number;
}>();

const initialReadMode = props.project.flip_settings?.readMode === 'scroll' ? 'scroll' : 'flip';
const viewMode = ref<'flip' | 'scroll'>(initialReadMode);
const scrollCarouselIndex = ref(0);
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
const pageAudioBusy = ref<Record<string, boolean>>({});
/** Merged from fetch poll so we do not Inertia-reload (which resets the flipbook) while page media jobs run. */
const pageMediaPoll = ref<
    Record<string, { video_url: string | null; video_generating: boolean; audio_url: string | null; audio_generating: boolean }>
>({});
const polledStoryCredits = ref<number | null>(null);
const currentFlipPageUuid = ref<string | null>(null);
const creditsModal = useCreditsModal();

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
const isPro = computed(() => props.feature_tier === 'pro' || props.feature_tier === 'elite');

const displayPages = computed((): PageRow[] =>
    props.pages.map((p) => {
        const o = pageMediaPoll.value[p.uuid];
        if (!o) {
            return p;
        }

        return {
            ...p,
            video_url: o.video_url ?? p.video_url,
            video_generating: o.video_generating,
            audio_url: o.audio_url ?? p.audio_url,
            audio_generating: o.audio_generating,
        };
    }),
);

const displayStoryCredits = computed(() =>
    polledStoryCredits.value !== null ? polledStoryCredits.value : props.story_credits,
);

/** Integer credits balance (avoids relational quirks with null/NaN from JSON). */
const storyCreditsBalance = computed(() => {
    const n = Number(displayStoryCredits.value);

    return Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0;
});

function toNonNegativeInt(value: unknown): number {
    const n = Number(value);

    if (!Number.isFinite(n) || n < 0) {
        return 0;
    }

    return Math.trunc(n);
}

const videoCreditCost = computed(() => toNonNegativeInt(props.video_credit_cost));
const audioCreditCost = computed(() => toNonNegativeInt(props.audio_credit_cost));

const canAffordSingleVideo = computed(() => storyCreditsBalance.value >= videoCreditCost.value);
const canAffordSingleAudio = computed(() => storyCreditsBalance.value >= audioCreditCost.value);

const mergedPageVideoBusy = computed(() => {
    const m: Record<string, boolean> = { ...pageVideoBusy.value };

    for (const p of displayPages.value) {
        if (p.video_generating) {
            m[p.uuid] = true;
        }
    }

    return m;
});

const mergedPageAudioBusy = computed(() => {
    const m: Record<string, boolean> = { ...pageAudioBusy.value };

    for (const p of displayPages.value) {
        if (p.audio_generating) {
            m[p.uuid] = true;
        }
    }

    return m;
});

const canGeneratePageVideoInFlipbook = computed(() => isPro.value && canAffordSingleVideo.value);
const canGeneratePageAudioInFlipbook = computed(
    () => !props.project.include_narration && canAffordSingleAudio.value,
);
const pageVideoActionHint = computed(() => {
    if (!isPro.value) {
        return 'Upgrade to Pro to generate page video.';
    }

    if (!canAffordSingleVideo.value) {
        return 'Not enough credits for a page video.';
    }

    return '';
});
const pageAudioActionHint = computed(() => {
    if (props.project.include_narration) {
        return '';
    }

    if (!canAffordSingleAudio.value) {
        return `Not enough credits for page narration (${audioCreditCost.value} required, ${storyCreditsBalance.value} available).`;
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

const currentScrollPage = computed(() => displayPages.value[scrollCarouselIndex.value] ?? null);

const scrollQuizQuestions = computed(() => {
    const p = currentScrollPage.value;

    if (!p) {
        return [];
    }

    return normalizeStoryQuizQuestions(p.quiz_questions);
});

const canScrollPrev = computed(() => scrollCarouselIndex.value > 0);
const canScrollNext = computed(() => scrollCarouselIndex.value < props.pages.length - 1);

function goScrollPrev(): void {
    if (!canScrollPrev.value) {
        return;
    }

    scrollCarouselIndex.value -= 1;
}

function goScrollNext(): void {
    if (!canScrollNext.value) {
        return;
    }

    scrollCarouselIndex.value += 1;
}

function openCreditsModal(): void {
    creditsModal.open();
}

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
        JSON.stringify(
            displayPages.value.map((p) => ({
                u: p.uuid,
                a: p.audio_url ?? '',
                v: p.video_url ?? '',
                ag: Boolean(p.audio_generating),
                vg: Boolean(p.video_generating),
            })),
        ),
    ].join('|'),
);

const illustrationStyleOptions = [
    { value: 'cartoon', label: 'Cartoon' },
    { value: 'watercolor', label: 'Watercolor' },
    { value: '3d', label: '3D render' },
    { value: 'storybook', label: 'Classic storybook' },
    { value: 'anime', label: 'Anime inspired' },
    { value: 'flat-vector', label: 'Flat vector' },
    { value: 'pencil-sketch', label: 'Pencil sketch' },
    { value: 'pixel-art', label: 'Pixel art' },
    { value: 'paper-collage', label: 'Paper collage' },
];

const hasAnyPageNarrationAudio = computed(() => displayPages.value.some((p) => Boolean(p.audio_url)));

/** Flip-to-play narration when there is something to play and no project-wide page video (avoids double audio). */
const flipbookPlayAudioOnFlip = computed(
    () =>
        !props.project.include_video &&
        (props.project.include_narration || hasAnyPageNarrationAudio.value),
);

const flipbookNarrationOffHint = computed(() => {
    if (props.project.include_video) {
        return 'Page videos include audio, so flip-to-play narration stays off to avoid overlapping sound.';
    }

    return '';
});

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
    return normalizeStoryQuizQuestions(raw);
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
            illustration_style: selectedIllustrationStyle.value,
            meta: { tts_voice: selectedTtsVoice.value },
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

function isPageVideoGenerating(page: PageRow): boolean {
    return Boolean(page.video_generating) || Boolean(pageVideoBusy.value[page.uuid]);
}

function isPageAudioGenerating(page: PageRow): boolean {
    return Boolean(page.audio_generating) || Boolean(pageAudioBusy.value[page.uuid]);
}

function canGenerateVideoForPage(page: PageRow): boolean {
    if (!isPro.value) {
        return false;
    }

    if (!canAffordSingleVideo.value) {
        return false;
    }

    if (isPageVideoGenerating(page)) {
        return false;
    }

    return Boolean(page.image_url);
}

async function queuePageVideoGeneration(page: PageRow): Promise<void> {
    if (!canGenerateVideoForPage(page)) {
        return;
    }

    const merged = displayPages.value.find((p) => p.uuid === page.uuid) ?? page;
    if (!merged.audio_url) {
        const ok = window.confirm(
            'This page has no narration audio yet. The generated video will not include a soundtrack (silent video). You can use “Generate narration” on the page first if you want audio in the clip.\n\nContinue with video generation?',
        );
        if (!ok) {
            return;
        }
    }

    pageVideoBusy.value = { ...pageVideoBusy.value, [page.uuid]: true };

    try {
        const csrf = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

        const response = await fetch(`/stories/${props.project.uuid}/pages/${page.uuid}/generate-video`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({}),
        });

        const data = (await response.json().catch(() => null)) as
            | { ok?: boolean; message?: string; story_credits?: number }
            | null;

        if (!response.ok || data?.ok === false) {
            pageVideoBusy.value = { ...pageVideoBusy.value, [page.uuid]: false };

            if ((data?.message ?? '').toLowerCase().includes('not enough credits')) {
                openCreditsModal();
            }

            return;
        }

        if (typeof data?.story_credits === 'number') {
            polledStoryCredits.value = data.story_credits;
        }

        pageMediaPoll.value = {
            ...pageMediaPoll.value,
            [page.uuid]: {
                video_url: pageMediaPoll.value[page.uuid]?.video_url ?? page.video_url,
                video_generating: true,
                audio_url: pageMediaPoll.value[page.uuid]?.audio_url ?? page.audio_url,
                audio_generating: pageMediaPoll.value[page.uuid]?.audio_generating ?? Boolean(page.audio_generating),
            },
        };

        void fetchPageMediaStatus();
    } catch {
        pageVideoBusy.value = { ...pageVideoBusy.value, [page.uuid]: false };
    }
}

function generateVideoForPage(page: PageRow): void {
    void queuePageVideoGeneration(page);
}

function canGenerateAudioForPage(page: PageRow): boolean {
    if (props.project.include_narration) {
        return false;
    }

    if (!canAffordSingleAudio.value) {
        return false;
    }

    if (Boolean(page.audio_url)) {
        return false;
    }

    if (isPageAudioGenerating(page)) {
        return false;
    }

    return Boolean((page.text_content ?? '').trim());
}

async function queuePageAudioGeneration(page: PageRow): Promise<void> {
    if (!canGenerateAudioForPage(page)) {
        return;
    }

    pageAudioBusy.value = { ...pageAudioBusy.value, [page.uuid]: true };

    try {
        const csrf = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

        const response = await fetch(`/stories/${props.project.uuid}/pages/${page.uuid}/generate-audio`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({}),
        });

        const data = (await response.json().catch(() => null)) as
            | { ok?: boolean; message?: string; story_credits?: number }
            | null;

        if (!response.ok || data?.ok === false) {
            pageAudioBusy.value = { ...pageAudioBusy.value, [page.uuid]: false };

            if ((data?.message ?? '').toLowerCase().includes('not enough credits')) {
                openCreditsModal();
            }

            return;
        }

        if (typeof data?.story_credits === 'number') {
            polledStoryCredits.value = data.story_credits;
        }

        pageMediaPoll.value = {
            ...pageMediaPoll.value,
            [page.uuid]: {
                video_url: pageMediaPoll.value[page.uuid]?.video_url ?? page.video_url,
                video_generating: pageMediaPoll.value[page.uuid]?.video_generating ?? Boolean(page.video_generating),
                audio_url: pageMediaPoll.value[page.uuid]?.audio_url ?? page.audio_url,
                audio_generating: true,
            },
        };

        void fetchPageMediaStatus();
    } catch {
        pageAudioBusy.value = { ...pageAudioBusy.value, [page.uuid]: false };
    }
}

function generateAudioForPage(page: PageRow): void {
    void queuePageAudioGeneration(page);
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

    void queuePageVideoGeneration(page);
}

function onFlipbookGeneratePageAudio(pageUuid: string): void {
    const page = props.pages.find((p) => p.uuid === pageUuid);

    if (!page) {
        return;
    }

    void queuePageAudioGeneration(page);
}

function setViewMode(mode: 'flip' | 'scroll'): void {
    if (viewMode.value === mode) {
        return;
    }

    viewMode.value = mode;

    const current = props.project.flip_settings ?? {};
    router.patch(
        `/stories/${props.project.uuid}`,
        {
            flip_settings: {
                ...current,
                readMode: mode,
            },
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
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
        if (scrollCarouselIndex.value > pages.length - 1) {
            scrollCarouselIndex.value = Math.max(0, pages.length - 1);
        }

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

const anyPageVideoGenerating = computed(() => displayPages.value.some((p) => Boolean(p.video_generating)));
const anyPageAudioGenerating = computed(() => displayPages.value.some((p) => Boolean(p.audio_generating)));

async function fetchPageMediaStatus(): Promise<void> {
    try {
        const res = await fetch(`/stories/${props.project.uuid}/page-media-status`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            return;
        }
        const data = (await res.json()) as {
            pages: {
                uuid: string;
                audio_url: string | null;
                audio_generating: boolean;
                video_url: string | null;
                video_generating: boolean;
            }[];
            story_credits: number;
        };
        const next: Record<
            string,
            { video_url: string | null; video_generating: boolean; audio_url: string | null; audio_generating: boolean }
        > = {};
        for (const p of data.pages) {
            next[p.uuid] = {
                video_url: p.video_url,
                video_generating: p.video_generating,
                audio_url: p.audio_url,
                audio_generating: p.audio_generating,
            };
        }
        pageMediaPoll.value = next;
        polledStoryCredits.value = data.story_credits;

        const nextVideoBusy = { ...pageVideoBusy.value };
        const nextAudioBusy = { ...pageAudioBusy.value };
        for (const p of data.pages) {
            if (!p.video_generating) {
                delete nextVideoBusy[p.uuid];
            }
            if (!p.audio_generating) {
                delete nextAudioBusy[p.uuid];
            }
        }
        pageVideoBusy.value = nextVideoBusy;
        pageAudioBusy.value = nextAudioBusy;
    } catch {
        /* ignore network errors */
    }
}

watch(
    () => props.pages,
    () => {
        pageMediaPoll.value = {};
        polledStoryCredits.value = null;
    },
);

onMounted(() => {
    poll = setInterval(() => {
        if (props.project.status === 'processing') {
            router.reload({ only: ['project', 'pages', 'story_credits'] });

            return;
        }

        if (anyPageVideoGenerating.value || anyPageAudioGenerating.value) {
            void fetchPageMediaStatus();
        }
    }, 8000);
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
        @buy-credits="openCreditsModal"
    />

    <div class="min-h-screen w-full bg-muted/30 dark:bg-muted/10">
        <StorySetupTopBar
            :project="project"
            :story-credits="displayStoryCredits"
            :feature-tier="props.feature_tier"
            :view-mode="viewMode"
            :show-view-mode="!isDraftReviewStage"
            @update:view-mode="setViewMode($event)"
        />

        <div class="mx-auto w-full max-w-440 space-y-5 px-4 py-4 sm:px-6">

            <!-- Mobile view-mode toggle -->
            <div
                v-if="!isDraftReviewStage"
                class="bg-card flex rounded-xl border border-border p-1 text-xs font-medium shadow-sm sm:hidden"
                role="group"
            >
                <button
                    type="button"
                    class="flex-1 rounded-lg px-3 py-2 transition-colors"
                    :class="viewMode === 'flip' ? 'bg-violet-500 text-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="setViewMode('flip')"
                >
                    Flip book
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-lg px-3 py-2 transition-colors"
                    :class="viewMode === 'scroll' ? 'bg-violet-500 text-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="setViewMode('scroll')"
                >
                    Scroll view
                </button>
            </div>

            <!-- Failed banner -->
            <div
                v-if="project.status === 'failed'"
                class="flex items-start gap-3 rounded-xl border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm"
            >
                <svg class="mt-0.5 size-4 shrink-0 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <p class="font-medium text-destructive">Generation failed</p>
                    <p class="text-muted-foreground mt-0.5 text-xs">Credits for completed steps were charged. Create a new project to retry.</p>
                </div>
            </div>

            <!-- ── DRAFT REVIEW STAGE ── -->
            <section v-if="isDraftReviewStage" class="grid gap-5 lg:grid-cols-5">

                <!-- Page list -->
                <div class="space-y-4 lg:col-span-3">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 items-center justify-center rounded-full bg-violet-500 text-xs font-bold text-white">1</span>
                        <h2 class="font-semibold">Review &amp; edit story text</h2>
                    </div>

                    <article
                        v-for="page in displayPages"
                        :key="page.uuid"
                        class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                    >
                        <!-- Page header -->
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                    {{ page.page_number }}
                                </span>
                                <span class="text-sm font-semibold">Page {{ page.page_number }}</span>
                                <!-- save state pill -->
                                <span
                                    v-if="pageSaveState[page.uuid] === 'saved'"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                                >Saved ✓</span>
                                <span
                                    v-else-if="pageSaveState[page.uuid] === 'unsaved'"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                >Unsaved</span>
                                <span
                                    v-else-if="pageSaveState[page.uuid] === 'error'"
                                    class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                >Save failed</span>
                            </div>
                            <div v-if="isPro" class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="!canGenerateVideoForPage(page)"
                                    :title="pageVideoActionHint"
                                    @click="generateVideoForPage(page)"
                                >
                                    <Loader2 v-if="isPageVideoGenerating(page)" class="mr-1.5 size-3.5 animate-spin" />
                                    <Clapperboard v-else class="mr-1.5 size-3.5" />
                                    {{
                                        isPageVideoGenerating(page)
                                            ? 'Video…'
                                            : page.video_url
                                              ? 'Regen video'
                                              : 'Gen video'
                                    }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    :disabled="Boolean(pageSaveBusy[page.uuid])"
                                    :class="pageSaveState[page.uuid] === 'unsaved' ? 'bg-violet-600 text-white hover:bg-violet-700' : ''"
                                    :variant="pageSaveState[page.uuid] === 'unsaved' ? 'default' : 'outline'"
                                    @click="savePageText(page.uuid)"
                                >
                                    {{ saveLabelFor(page.uuid) }}
                                </Button>
                            </div>
                        </div>

                        <!-- Credit / pro hints -->
                        <div class="px-4 pt-3 pb-1 flex flex-wrap gap-2 text-xs">
                            <template v-if="isPro">
                                <span class="text-muted-foreground">Video: {{ props.video_credit_cost }} credits/page</span>
                                <span v-if="!canAffordSingleVideo" class="text-destructive">· Not enough credits</span>
                                <button v-if="!canAffordSingleVideo" class="text-violet-600 hover:underline" type="button" @click="openCreditsModal">Buy credits</button>
                            </template>
                            <span v-else class="text-muted-foreground">Page video is available on Pro and Elite.</span>
                        </div>

                        <!-- Textarea -->
                        <div class="px-4 pb-4 pt-2">
                            <textarea
                                v-model="pageDraftText[page.uuid]"
                                rows="6"
                                class="border-input bg-background focus-visible:ring-ring w-full rounded-xl border px-3 py-2.5 text-sm leading-relaxed focus-visible:outline-none focus-visible:ring-1"
                                @input="markPageTextDirty(page.uuid)"
                            />
                        </div>

                        <!-- Quiz section -->
                        <div v-if="project.include_quiz" class="border-t border-border/60 px-4 py-3">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Quiz</p>
                                <Button type="button" size="sm" variant="outline" @click="addQuizQuestion(page.uuid)">
                                    + Add question
                                </Button>
                            </div>
                            <div v-if="(pageDraftQuiz[page.uuid] ?? []).length > 0" class="space-y-3">
                                <div
                                    v-for="(q, idx) in pageDraftQuiz[page.uuid]"
                                    :key="`${page.uuid}-q-${idx}`"
                                    class="rounded-xl border border-border/60 bg-muted/30 p-3"
                                >
                                    <div class="mb-2 flex items-center justify-between">
                                        <p class="text-xs font-semibold">Q{{ idx + 1 }}</p>
                                        <button type="button" class="text-xs text-destructive hover:underline" @click="removeQuizQuestion(page.uuid, idx)">Remove</button>
                                    </div>
                                    <input
                                        v-model="q.question"
                                        type="text"
                                        placeholder="Question"
                                        class="border-input bg-background mb-2 w-full rounded-lg border px-2 py-1.5 text-sm"
                                        @input="markPageQuizDirty(page.uuid)"
                                    />
                                    <div class="space-y-1.5 mb-2">
                                        <div v-for="(choice, cIdx) in q.choices" :key="`${page.uuid}-q-${idx}-c-${cIdx}`" class="flex items-center gap-2">
                                            <input
                                                v-model="q.choices[cIdx]"
                                                type="text"
                                                :placeholder="`Choice ${cIdx + 1}`"
                                                class="border-input bg-background w-full rounded-lg border px-2 py-1.5 text-sm"
                                                @input="markPageQuizDirty(page.uuid)"
                                            />
                                            <button type="button" class="text-xs text-muted-foreground hover:text-destructive" @click="removeQuizChoice(page.uuid, idx, cIdx)">✕</button>
                                        </div>
                                    </div>
                                    <button type="button" class="text-xs font-medium text-violet-600 hover:underline" @click="addQuizChoice(page.uuid, idx)">+ Add choice</button>
                                    <input
                                        v-model="q.answer"
                                        type="text"
                                        placeholder="Correct answer"
                                        class="border-input bg-background mt-2 w-full rounded-lg border px-2 py-1.5 text-sm"
                                        @input="markPageQuizDirty(page.uuid)"
                                    />
                                </div>
                            </div>
                            <p v-else class="text-muted-foreground text-xs">No questions yet.</p>
                        </div>
                    </article>
                </div>

                <!-- Settings sidebar -->
                <aside class="lg:col-span-2 lg:sticky lg:top-20 h-fit space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 items-center justify-center rounded-full bg-violet-500 text-xs font-bold text-white">2</span>
                        <h2 class="font-semibold">Choose media to generate</h2>
                    </div>

                    <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-4 dark:border-sidebar-border space-y-3">
                        <p class="text-muted-foreground text-xs">Select what assets to generate for each page, then click Continue.</p>

                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-border/60 px-3 py-2.5 transition-colors hover:bg-muted/30">
                            <div>
                                <p class="text-sm font-medium">Illustrations</p>
                                <p class="text-muted-foreground text-xs">AI image per page</p>
                            </div>
                            <span class="relative inline-flex shrink-0">
                                <input v-model="generateImages" :disabled="generateVideo" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-violet-500 peer-disabled:opacity-50 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-white ml-0.5 size-5 rounded-full shadow transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-border/60 px-3 py-2.5 transition-colors hover:bg-muted/30">
                            <div>
                                <p class="text-sm font-medium">Narration (TTS)</p>
                                <p class="text-muted-foreground text-xs">AI voice audio per page</p>
                            </div>
                            <span class="relative inline-flex shrink-0">
                                <input v-model="generateAudio" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-blue-500 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-white ml-0.5 size-5 rounded-full shadow transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>

                        <label
                            v-if="isPro"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-border/60 px-3 py-2.5 transition-colors hover:bg-muted/30"
                            :class="{ 'opacity-50 pointer-events-none': !project.include_video }"
                        >
                            <div>
                                <p class="text-sm font-medium flex items-center gap-1.5">
                                    Page Video
                                    <span class="rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">Pro</span>
                                </p>
                                <p class="text-muted-foreground text-xs">AI video per illustration</p>
                            </div>
                            <span class="relative inline-flex shrink-0">
                                <input v-model="generateVideo" :disabled="!project.include_video" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-violet-500 peer-disabled:opacity-50 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-white ml-0.5 size-5 rounded-full shadow transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>

                        <Button type="button" class="w-full bg-violet-600 text-white hover:bg-violet-700" @click="startMediaGeneration">
                            {{ generateImages || generateAudio || generateVideo ? '✨ Continue: generate media' : 'Finish as text-only' }}
                        </Button>

                        <p v-if="hasUnsavedPageChanges" class="text-xs text-amber-600">
                            ⚠ {{ unsavedPagesCount }} page(s) have unsaved edits.
                        </p>
                    </div>

                    <!-- Advanced options -->
                    <details class="group rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border overflow-hidden">
                        <summary class="flex cursor-pointer items-center justify-between px-4 py-3 text-sm font-medium hover:bg-muted/30 transition-colors">
                            <span>Advanced options</span>
                            <span class="text-muted-foreground transition-transform group-open:rotate-180">▾</span>
                        </summary>
                        <div class="border-t border-border/60 space-y-3 p-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Illustration style</label>
                                <select v-model="selectedIllustrationStyle" class="border-input bg-background w-full rounded-xl border px-3 py-2 text-sm">
                                    <option v-for="opt in illustrationStyleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Narration voice</label>
                                <select v-model="selectedTtsVoice" class="border-input bg-background w-full rounded-xl border px-3 py-2 text-sm">
                                    <option v-for="opt in ttsVoiceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <Button type="button" size="sm" variant="outline" class="w-full" :disabled="advancedSaveBusy" @click="saveAdvancedSettings">
                                {{ advancedSaveBusy ? 'Saving…' : advancedDirty ? 'Save advanced settings' : '✓ Advanced settings saved' }}
                            </Button>
                        </div>
                    </details>
                </aside>
            </section>

            <!-- ── POST-DRAFT: FLIPBOOK / SCROLL ── -->
            <section class="min-w-0 space-y-4">

                <!-- Flip view -->
                <div
                    v-if="!isDraftReviewStage && viewMode === 'flip' && pages.length > 0"
                    ref="flipbookSectionRef"
                    tabindex="-1"
                    class="relative w-full rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-3 dark:border-sidebar-border"
                >
                    <StoryFlipbook
                        :key="flipbookKey"
                        :title="project.title"
                        :pages="displayPages"
                        :play-audio-on-flip="flipbookPlayAudioOnFlip"
                        :narration-unavailable-hint="flipbookNarrationOffHint"
                        :story-uuid="project.uuid"
                        :include-quiz="project.include_quiz"
                        :gameplay-enabled="project.flip_gameplay_enabled"
                        :setup-mode="true"
                        :cover-front="project.cover_front"
                        :cover-back="project.cover_back"
                        :flip-settings="project.flip_settings"
                        :show-page-video-action="isPro"
                        :show-video-media-settings="isPro"
                        :can-generate-page-video="canGeneratePageVideoInFlipbook"
                        :page-video-busy="mergedPageVideoBusy"
                        :page-video-action-hint="pageVideoActionHint"
                        :show-page-audio-action="!project.include_narration"
                        :can-generate-page-audio="canGeneratePageAudioInFlipbook"
                        :page-audio-busy="mergedPageAudioBusy"
                        :page-audio-action-hint="pageAudioActionHint"
                        @view-page-change="onFlipViewPageChange"
                        @generate-page-video="onFlipbookGeneratePageVideo"
                        @generate-page-audio="onFlipbookGeneratePageAudio"
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

                <div
                    v-else-if="!isDraftReviewStage && viewMode === 'flip' && pages.length === 0"
                    class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-card/50 py-16 text-center shadow-sm"
                >
                    <div class="flex size-12 items-center justify-center rounded-full bg-muted">
                        <svg class="size-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <p class="text-muted-foreground text-sm">Pages not ready yet — the flipbook appears once text is generated.</p>
                </div>

                <!-- Scroll view -->
                <div v-if="!isDraftReviewStage && viewMode === 'scroll'" class="flex flex-col gap-4">
                    <p v-if="pages.length === 0" class="text-muted-foreground rounded-2xl border border-dashed border-border bg-card/50 py-12 text-center text-sm">
                        No pages yet — generation may still be running.
                    </p>

                    <template v-else>
                        <!-- Page nav bar -->
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-sidebar-border/70 bg-card shadow-sm px-4 py-2.5 dark:border-sidebar-border">
                            <Button type="button" size="sm" variant="outline" :disabled="!canScrollPrev" @click="goScrollPrev">
                                ← Prev
                            </Button>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold">Page {{ currentScrollPage?.page_number }}</span>
                                <span class="text-muted-foreground text-xs">of {{ pages.length }}</span>
                            </div>
                            <!-- dot indicators -->
                            <div class="hidden sm:flex items-center gap-1">
                                <button
                                    v-for="(p, i) in pages"
                                    :key="p.uuid"
                                    type="button"
                                    class="size-2 rounded-full transition-all"
                                    :class="i === scrollCarouselIndex ? 'bg-violet-500 scale-125' : 'bg-muted-foreground/30 hover:bg-muted-foreground/60'"
                                    :title="`Page ${p.page_number}`"
                                    @click="scrollCarouselIndex = i"
                                />
                            </div>
                            <Button type="button" size="sm" variant="outline" :disabled="!canScrollNext" @click="goScrollNext">
                                Next →
                            </Button>
                        </div>

                        <!-- Page card -->
                        <article
                            v-if="currentScrollPage"
                            :key="currentScrollPage.uuid"
                            class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm overflow-hidden dark:border-sidebar-border"
                        >
                            <!-- Card header -->
                            <div class="flex items-center justify-between gap-2 border-b border-border/60 bg-muted/30 px-5 py-3">
                                <h2 class="font-semibold">Page {{ currentScrollPage.page_number }}</h2>
                                <div v-if="!project.include_narration || isPro" class="flex flex-wrap items-center justify-end gap-2">
                                    <template
                                        v-if="
                                            !project.include_narration &&
                                            (!currentScrollPage.audio_url || isPageAudioGenerating(currentScrollPage))
                                        "
                                    >
                                        <span class="text-muted-foreground text-xs">Narration: {{ props.audio_credit_cost }} cr</span>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            :disabled="!canGenerateAudioForPage(currentScrollPage)"
                                            :title="pageAudioActionHint"
                                            @click="generateAudioForPage(currentScrollPage)"
                                        >
                                            <Loader2 v-if="isPageAudioGenerating(currentScrollPage)" class="mr-1.5 size-3.5 animate-spin" />
                                            <Volume2 v-else class="mr-1.5 size-3.5" />
                                            {{ isPageAudioGenerating(currentScrollPage) ? 'Audio…' : 'Gen narration' }}
                                        </Button>
                                    </template>
                                    <template v-if="isPro">
                                        <span class="text-muted-foreground text-xs">Video: {{ props.video_credit_cost }} cr</span>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            :disabled="!canGenerateVideoForPage(currentScrollPage)"
                                            :title="pageVideoActionHint"
                                            @click="generateVideoForPage(currentScrollPage)"
                                        >
                                            <Loader2 v-if="isPageVideoGenerating(currentScrollPage)" class="mr-1.5 size-3.5 animate-spin" />
                                            <Clapperboard v-else class="mr-1.5 size-3.5" />
                                            {{
                                                isPageVideoGenerating(currentScrollPage)
                                                    ? 'Video…'
                                                    : currentScrollPage.video_url
                                                      ? 'Regen video'
                                                      : 'Gen video'
                                            }}
                                        </Button>
                                    </template>
                                </div>
                            </div>

                            <!-- Card body -->
                            <div class="grid gap-0 lg:grid-cols-2">
                                <!-- Text + quiz -->
                                <div class="flex flex-col gap-4 p-5 border-b lg:border-b-0 lg:border-r border-border/60">
                                    <p class="text-sm leading-relaxed">{{ currentScrollPage.text_content }}</p>

                                    <div
                                        v-if="project.include_quiz && scrollQuizQuestions.length > 0"
                                        class="overflow-hidden rounded-2xl border border-primary/20 bg-card/80 shadow-sm"
                                    >
                                        <StoryQuizSheet
                                            :key="`${currentScrollPage.uuid}-scroll-quiz`"
                                            :story-uuid="project.uuid"
                                            :page-uuid="currentScrollPage.uuid"
                                            :questions="scrollQuizQuestions"
                                            :editable="false"
                                            compact
                                        />
                                    </div>

                                    <audio
                                        v-if="currentScrollPage.audio_url"
                                        :key="currentScrollPage.audio_url"
                                        :src="currentScrollPage.audio_url"
                                        controls
                                        class="w-full rounded-lg border border-border/60 bg-muted/20 p-1"
                                    />
                                    <p v-else-if="project.include_narration" class="text-xs text-muted-foreground">
                                        Audio pending…
                                    </p>
                                    <p v-else class="text-xs text-muted-foreground">No narration for this page yet.</p>

                                    <div v-if="currentScrollPage.asset_errors && Object.keys(currentScrollPage.asset_errors).length" class="rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive">
                                        <p class="font-semibold mb-1">Asset errors</p>
                                        <ul class="list-inside list-disc space-y-0.5">
                                            <li v-for="(msg, key) in currentScrollPage.asset_errors" :key="key">{{ key }}: {{ msg }}</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Media -->
                                <div class="flex flex-col gap-3 p-5">
                                    <div v-if="currentScrollPage.video_url" class="overflow-hidden rounded-xl border bg-black">
                                        <video
                                            :key="currentScrollPage.video_url"
                                            :src="videoPlaybackSrc(currentScrollPage.video_url) ?? currentScrollPage.video_url"
                                            :poster="currentScrollPage.image_url ?? undefined"
                                            controls
                                            playsinline
                                            preload="metadata"
                                            class="max-h-72 w-full object-contain"
                                        />
                                    </div>
                                    <div v-else-if="currentScrollPage.image_url" class="overflow-hidden rounded-xl border">
                                        <img
                                            :src="currentScrollPage.image_url"
                                            :alt="`Illustration page ${currentScrollPage.page_number}`"
                                            draggable="false"
                                            class="max-h-72 w-full select-none object-contain [-webkit-user-drag:none]"
                                        />
                                    </div>
                                    <div v-else class="flex h-40 items-center justify-center rounded-xl border border-dashed border-border bg-muted/30 text-sm text-muted-foreground">
                                        Image pending…
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </section>
        </div>
    </div>
</template>
