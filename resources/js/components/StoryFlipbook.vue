import '../../css/flipbook-realism.css';
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { JQueryStatic } from 'jquery';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { createApp, h, computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import StoryFlipbookSetupPanel from '@/components/StoryFlipbookSetupPanel.vue';
import type {FlipbookSetupSettings} from '@/components/StoryFlipbookSetupPanel.vue';
import StoryQuizSheet from '@/components/StoryQuizSheet.vue';
import type {QuizRow} from '@/components/StoryQuizSheet.vue';
import { Button } from '@/components/ui/button';
import { coverFrameRootClass } from '@/lib/coverFrames';

export type FlipbookPage = {
    uuid: string;
    page_number: number;
    text_content: string | null;
    quiz_questions: unknown;
    asset_errors: Record<string, string> | null;
    image_url: string | null;
    audio_url: string | null;
    video_url: string | null;
    /** Server-side: page video job pending or running (author view). */
    video_generating?: boolean;
};

export type CoverConfigJson = {
    kind: string;
    color?: string;
    angle?: number;
    from?: string;
    to?: string;
    path?: string;
    url?: string;
    prompt?: string;
    /** Decorative hard-cover border / embossing template */
    frame?: string;
} | null;

const STORAGE_KEY = 'ai-storybook-flip-settings-v1';

const props = withDefaults(
    defineProps<{
        title: string;
        pages: FlipbookPage[];
        /** Initial default when no saved settings */
        playAudioOnFlip?: boolean;
        storyUuid?: string;
        includeQuiz?: boolean;
        /** Persisted on the story; inserts quiz sheets after each page that has questions. */
        gameplayEnabled?: boolean;
        /** When true, quiz sheets allow editing and saving questions. */
        setupMode?: boolean;
        coverFront?: CoverConfigJson;
        coverBack?: CoverConfigJson;
        /** Saved flip UI (audio, auto-advance, Turn options). Public reader uses this; author overlays localStorage then server wins. */
        flipSettings?: Record<string, unknown> | null;
        /** Show per-page video action button in the page image area. */
        showPageVideoAction?: boolean;
        /** Global eligibility gate (tier + credits); per-page checks still apply. */
        canGeneratePageVideo?: boolean;
        /** Busy state per page while request is in flight. */
        pageVideoBusy?: Record<string, boolean>;
        /** Optional reason shown as disabled button tooltip. */
        pageVideoActionHint?: string;
        /** When playAudioOnFlip is false but narration exists (e.g. video stories), explain why the toggle is off. */
        narrationUnavailableHint?: string;
        /** Hide video playback / default media controls in setup (e.g. Basic tier readers). */
        showVideoMediaSettings?: boolean;
    }>(),
    {
        playAudioOnFlip: true,
        storyUuid: '',
        includeQuiz: false,
        gameplayEnabled: true,
        setupMode: false,
        coverFront: null,
        coverBack: null,
        flipSettings: null,
        showPageVideoAction: false,
        canGeneratePageVideo: false,
        pageVideoBusy: () => ({}),
        pageVideoActionHint: '',
        narrationUnavailableHint: '',
        showVideoMediaSettings: true,
    },
);

const emit = defineEmits<{
    'view-page-change': [pageUuid: string | null];
    'generate-page-video': [pageUuid: string];
}>();

const flipRoot = ref<HTMLElement | null>(null);
const pageAudioRef = ref<HTMLAudioElement | null>(null);
const ready = ref(false);
const currentContentPageNumbers = ref<number[]>([]);
/** In double-page mode, shifts the stage so a single visible cover (closed front or closed back) sits centered. */
const bookNudgePx = ref(0);
let jq: JQueryStatic | null = null;

/** uuid → Vue app, so we never double-mount and can unmount individually when Turn removes nodes. */
const gameMountedApps = new Map<string, ReturnType<typeof createApp>>();
let gameMountObserver: MutationObserver | null = null;

function unmountGameApps(): void {
    gameMountObserver?.disconnect();
    gameMountObserver = null;
    gameMountedApps.forEach((app) => {
        try {
            app.unmount();
        } catch {
            /* already detached */
        }
    });
    gameMountedApps.clear();
}

function normalizeQuiz(raw: unknown): QuizRow[] {
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
            // ignore
        }
    }

    return arr.map((item) => {
        if (!item || typeof item !== 'object') {
            return { question: '', choices: ['', ''], answer: '' };
        }

        const o = item as Record<string, unknown>;
        const choices = Array.isArray(o.choices) ? o.choices.map((c) => String(c)) : [];

        return {
            question: String(o.question ?? ''),
            choices: choices.length > 0 ? choices : ['A', 'B'],
            answer: String(o.answer ?? ''),
        };
    });
}

function pageHasQuiz(p: FlipbookPage): boolean {
    return normalizeQuiz(p.quiz_questions).length > 0;
}

const hasQuizPages = computed(() => props.pages.some((p) => pageHasQuiz(p)));

type MiddleSlot =
    | { type: 'content'; page: FlipbookPage; idx: number }
    | { type: 'game'; page: FlipbookPage; idx: number };

function middleSlots(): MiddleSlot[] {
    const out: MiddleSlot[] = [];
    props.pages.forEach((p, idx) => {
        out.push({ type: 'content', page: p, idx });

        if (props.gameplayEnabled && props.includeQuiz && pageHasQuiz(p)) {
            out.push({ type: 'game', page: p, idx });
        }
    });

    return out;
}

function coverHardStyle(cfg: CoverConfigJson): Record<string, string> {
    if (!cfg?.kind) {
        return {};
    }

    if (cfg.kind === 'solid' && cfg.color) {
        return { background: cfg.color };
    }

    if (cfg.kind === 'gradient') {
        const a = cfg.angle ?? 135;
        const from = cfg.from ?? '#6366f1';
        const to = cfg.to ?? '#ec4899';

        return { background: `linear-gradient(${a}deg, ${from}, ${to})` };
    }

    if ((cfg.kind === 'image' || cfg.kind === 'gif' || cfg.kind === 'ai_image') && cfg.url) {
        return {
            backgroundImage: `url("${cfg.url.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}")`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
        };
    }

    return {};
}

function setGameplayEnabled(checked: boolean): void {
    if (!props.storyUuid) {
        return;
    }

    gameplayEnabledLocal.value = checked;
    gameplayToggleBusy.value = true;
    router.patch(
        `/stories/${props.storyUuid}`,
        { flip_gameplay_enabled: checked },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                gameplayEnabledLocal.value = props.gameplayEnabled;
            },
            onFinish: () => {
                gameplayToggleBusy.value = false;
            },
        },
    );
}

const gameplayEnabledLocal = ref(props.gameplayEnabled);
const gameplayToggleBusy = ref(false);

/** Turn.js sheet order before content starts: front hard cover + front endpaper. */
const FRONT_FIXED_SHEETS = 2;

type SpreadAudioMode = 'first' | 'sequence';
type AutoAdvanceMode = 'off' | 'timer' | 'afterAudio';
type VideoPlaybackMode = 'click' | 'auto';
type DefaultMediaMode = 'video' | 'image';

const settings = reactive<FlipbookSetupSettings>({
    audioOnFlip: props.playAudioOnFlip,
    spreadAudio: 'sequence' as SpreadAudioMode,
    autoAdvance: 'off' as AutoAdvanceMode,
    timerDelaySec: 5,
    flipDuration: 1800,
    display: 'double' as 'single' | 'double',
    gradients: true,
    acceleration: true,
    elevation: 200,
    corners: 'all',
    pagesInDOM: 10,
    bookZoomPercent: 100,
    videoPlaybackMode: 'click' as VideoPlaybackMode,
    defaultMediaMode: 'video' as DefaultMediaMode,
    dragFlipEnabled: true,
    pageMediaOverrides: {} as Record<string, DefaultMediaMode>,
});
let suppressSettingsSync = false;
let suppressDepth = 0;
let lastPersistedFlipSettingsJson = '';

function withSuppressedSettingsSync(fn: () => void): void {
    suppressDepth += 1;
    suppressSettingsSync = true;
    try {
        fn();
    } finally {
        // Keep suppression through the next microtask so watcher callbacks
        // triggered by this assignment burst cannot immediately persist again.
        queueMicrotask(() => {
            suppressDepth = Math.max(0, suppressDepth - 1);
            suppressSettingsSync = suppressDepth > 0;
        });
    }
}

function localStorageKey(): string {
    return props.storyUuid ? `${STORAGE_KEY}:${props.storyUuid}` : STORAGE_KEY;
}

function defaultFlipCore(): typeof settings {
    return {
        audioOnFlip: props.playAudioOnFlip,
        spreadAudio: 'sequence',
        autoAdvance: 'off',
        timerDelaySec: 5,
        flipDuration: 650,
        display: 'double',
        gradients: true,
        acceleration: true,
        elevation: 48,
        corners: 'all',
        pagesInDOM: 10,
        bookZoomPercent: 100,
        videoPlaybackMode: 'click',
        defaultMediaMode: 'video',
        dragFlipEnabled: true,
        pageMediaOverrides: {},
    };
}

function applyFlipPayloadFromServer(o: Record<string, unknown>): void {
    withSuppressedSettingsSync(() => {
    if (typeof o.audioOnFlip === 'boolean') {
        settings.audioOnFlip = o.audioOnFlip;
    }

    if (o.spreadAudio === 'first' || o.spreadAudio === 'sequence') {
        settings.spreadAudio = o.spreadAudio;
    }

    if (o.autoAdvance === 'off' || o.autoAdvance === 'timer' || o.autoAdvance === 'afterAudio') {
        settings.autoAdvance = o.autoAdvance;
    }

    if (typeof o.timerDelaySec === 'number' && o.timerDelaySec >= 2 && o.timerDelaySec <= 30) {
        settings.timerDelaySec = o.timerDelaySec;
    }

    if (typeof o.flipDuration === 'number' && o.flipDuration >= 250 && o.flipDuration <= 1500) {
        settings.flipDuration = o.flipDuration;
    }

    if (o.display === 'single' || o.display === 'double') {
        settings.display = o.display;
    }

    if (typeof o.gradients === 'boolean') {
        settings.gradients = o.gradients;
    }

    if (typeof o.acceleration === 'boolean') {
        settings.acceleration = o.acceleration;
    }

    if (typeof o.elevation === 'number' && o.elevation >= 0 && o.elevation <= 120) {
        settings.elevation = o.elevation;
    }

    if (typeof o.bookZoomPercent === 'number' && o.bookZoomPercent >= 70 && o.bookZoomPercent <= 130) {
        settings.bookZoomPercent = o.bookZoomPercent;
    }

    if (o.videoPlaybackMode === 'click' || o.videoPlaybackMode === 'auto') {
        settings.videoPlaybackMode = o.videoPlaybackMode;
    }

    if (o.defaultMediaMode === 'video' || o.defaultMediaMode === 'image') {
        settings.defaultMediaMode = o.defaultMediaMode;
    }

    if (typeof o.dragFlipEnabled === 'boolean') {
        settings.dragFlipEnabled = o.dragFlipEnabled;
    }

    if (o.pageMediaOverrides && typeof o.pageMediaOverrides === 'object' && !Array.isArray(o.pageMediaOverrides)) {
        const next: Record<string, DefaultMediaMode> = {};

        for (const [uuid, mode] of Object.entries(o.pageMediaOverrides as Record<string, unknown>)) {
            if ((mode === 'video' || mode === 'image') && typeof uuid === 'string' && uuid !== '') {
                next[uuid] = mode;
            }
        }

        settings.pageMediaOverrides = next;
    }
    });
}

/** Defaults → optional per-story localStorage (author only) → server flip_settings (author & public). */
function initializeFlipUiSettings(): void {
    Object.assign(settings, defaultFlipCore());

    if (props.setupMode) {
        loadSettings();
    }

    const fs = props.flipSettings;

    if (fs && typeof fs === 'object' && !Array.isArray(fs) && Object.keys(fs).length > 0) {
        applyFlipPayloadFromServer(fs as Record<string, unknown>);
    }
}

function loadSettings(): void {
    try {
        const raw = localStorage.getItem(localStorageKey());

        if (!raw) {
            return;
        }

        const o = JSON.parse(raw) as Partial<typeof settings>;

        if (typeof o.audioOnFlip === 'boolean') {
            settings.audioOnFlip = o.audioOnFlip;
        }

        if (o.spreadAudio === 'first' || o.spreadAudio === 'sequence') {
            settings.spreadAudio = o.spreadAudio;
        }

        if (o.autoAdvance === 'off' || o.autoAdvance === 'timer' || o.autoAdvance === 'afterAudio') {
            settings.autoAdvance = o.autoAdvance;
        }

        if (typeof o.timerDelaySec === 'number' && o.timerDelaySec >= 2 && o.timerDelaySec <= 30) {
            settings.timerDelaySec = o.timerDelaySec;
        }

        if (typeof o.flipDuration === 'number' && o.flipDuration >= 250 && o.flipDuration <= 1500) {
            settings.flipDuration = o.flipDuration;
        }

        if (o.display === 'single' || o.display === 'double') {
            settings.display = o.display;
        }

        if (typeof o.gradients === 'boolean') {
            settings.gradients = o.gradients;
        }

        if (typeof o.acceleration === 'boolean') {
            settings.acceleration = o.acceleration;
        }

        if (typeof o.elevation === 'number' && o.elevation >= 0 && o.elevation <= 120) {
            settings.elevation = o.elevation;
        }

        if (typeof o.bookZoomPercent === 'number' && o.bookZoomPercent >= 70 && o.bookZoomPercent <= 130) {
            settings.bookZoomPercent = o.bookZoomPercent;
        }

        if (o.videoPlaybackMode === 'click' || o.videoPlaybackMode === 'auto') {
            settings.videoPlaybackMode = o.videoPlaybackMode;
        }

        if (o.defaultMediaMode === 'video' || o.defaultMediaMode === 'image') {
            settings.defaultMediaMode = o.defaultMediaMode;
        }

        if (typeof o.dragFlipEnabled === 'boolean') {
            settings.dragFlipEnabled = o.dragFlipEnabled;
        }

        if (o.pageMediaOverrides && typeof o.pageMediaOverrides === 'object' && !Array.isArray(o.pageMediaOverrides)) {
            const next: Record<string, DefaultMediaMode> = {};

            for (const [uuid, mode] of Object.entries(o.pageMediaOverrides as Record<string, unknown>)) {
                if ((mode === 'video' || mode === 'image') && typeof uuid === 'string' && uuid !== '') {
                    next[uuid] = mode;
                }
            }

            settings.pageMediaOverrides = next;
        }
    } catch {
        /* ignore */
    }
}

function saveSettings(): void {
    if (!props.setupMode) {
        return;
    }

    try {
        localStorage.setItem(localStorageKey(), JSON.stringify({ ...settings }));
    } catch {
        /* ignore */
    }
}

let persistFlipTimer: ReturnType<typeof setTimeout> | null = null;

function serializeFlipSettingsForServer(): Record<string, string | number | boolean> {
    return {
        audioOnFlip: settings.audioOnFlip,
        spreadAudio: settings.spreadAudio,
        autoAdvance: settings.autoAdvance,
        timerDelaySec: settings.timerDelaySec,
        flipDuration: settings.flipDuration,
        display: settings.display,
        gradients: settings.gradients,
        acceleration: settings.acceleration,
        elevation: settings.elevation,
        bookZoomPercent: settings.bookZoomPercent,
        videoPlaybackMode: settings.videoPlaybackMode,
        defaultMediaMode: settings.defaultMediaMode,
        dragFlipEnabled: settings.dragFlipEnabled,
        pageMediaOverrides: settings.pageMediaOverrides,
    };
}

function schedulePersistFlipSettingsToServer(): void {
    if (!props.setupMode || !props.storyUuid) {
        return;
    }

    const payload = serializeFlipSettingsForServer();
    const payloadJson = JSON.stringify(payload);
    if (payloadJson === lastPersistedFlipSettingsJson) {
        return;
    }

    if (persistFlipTimer !== null) {
        clearTimeout(persistFlipTimer);
    }

    persistFlipTimer = window.setTimeout(() => {
        persistFlipTimer = null;
        const latestPayload = serializeFlipSettingsForServer();
        const latestPayloadJson = JSON.stringify(latestPayload);

        if (latestPayloadJson === lastPersistedFlipSettingsJson) {
            return;
        }

        lastPersistedFlipSettingsJson = latestPayloadJson;
        router.patch(
            `/stories/${props.storyUuid}`,
            { flip_settings: latestPayload },
            { preserveScroll: true },
        );
    }, 450);
}

watch(
    () => ({ ...settings }),
    () => {
        if (suppressSettingsSync) {
            return;
        }

        saveSettings();
        schedulePersistFlipSettingsToServer();
    },
    { deep: true },
);

watch(
    () => props.flipSettings,
    (nextSettings) => {
        if (nextSettings && typeof nextSettings === 'object' && !Array.isArray(nextSettings)) {
            lastPersistedFlipSettingsJson = JSON.stringify(nextSettings);
            applyFlipPayloadFromServer(nextSettings as Record<string, unknown>);
        }
    },
    { deep: true },
);

watch(
    () => props.gameplayEnabled,
    (enabled) => {
        gameplayEnabledLocal.value = enabled;
    },
);

watch(
    () => props.playAudioOnFlip,
    (canPlayNarration) => {
        if (!canPlayNarration) {
            withSuppressedSettingsSync(() => {
                settings.audioOnFlip = false;

                if (settings.autoAdvance === 'afterAudio') {
                    settings.autoAdvance = 'off';
                }
            });
        }
    },
    { immediate: true },
);

watch(
    () => settings.audioOnFlip,
    (enabled) => {
        if (!enabled && settings.autoAdvance === 'afterAudio') {
            settings.autoAdvance = 'off';
        }
    },
);

watch(
    () => settings.videoPlaybackMode,
    (mode) => {
        if (mode === 'auto') {
            playVideoForSpread(getCurrentView());
            return;
        }

        pausePageVideos();
    },
);

let advanceTimer: ReturnType<typeof setTimeout> | null = null;

function clearAdvanceTimer(): void {
    if (advanceTimer !== null) {
        clearTimeout(advanceTimer);
        advanceTimer = null;
    }
}

/** Pending deferred start of a narration clip (cleared on flip / pause). */
let narrationStartTimer: ReturnType<typeof setTimeout> | null = null;

function clearNarrationStartTimer(): void {
    if (narrationStartTimer !== null) {
        clearTimeout(narrationStartTimer);
        narrationStartTimer = null;
    }
}

function contentPageRange(): { start: number; end: number } {
    const start = FRONT_FIXED_SHEETS + 1;
    const mid = middleSlots();
    const end = start + mid.length - 1;

    return { start, end };
}

/** Narration off when every visible story sheet is a quiz page (avoids voiceover on game-only spreads). */
function viewIsOnlyGameSheets(view: number[]): boolean {
    const { start, end } = contentPageRange();
    const mid = middleSlots();
    const inMiddle = view.filter((tp) => tp >= start && tp <= end);

    if (inMiddle.length === 0) {
        return false;
    }

    return inMiddle.every((tp) => mid[tp - start]?.type === 'game');
}

/** Story page indices for the spread, left → right (turn page order), no duplicate clips. */
function storyIndicesInView(view: number[]): number[] {
    const { start, end } = contentPageRange();
    const mid = middleSlots();
    const seen = new Set<number>();
    const ordered: number[] = [];

    for (const p of [...view].filter((n) => n > 0).sort((a, b) => a - b)) {
        if (p < start || p > end) {
            continue;
        }

        const slot = mid[p - start];

        if (slot?.type === 'content' && !seen.has(slot.idx)) {
            seen.add(slot.idx);
            ordered.push(slot.idx);
        }
    }

    return ordered;
}

function updateCurrentContentPageNumbers(view: number[]): void {
    const indices = storyIndicesInView(view);
    currentContentPageNumbers.value = indices
        .map((idx) => props.pages[idx]?.page_number)
        .filter((num): num is number => typeof num === 'number');
}

function isPlayableAudioUrl(url: string | null | undefined): boolean {
    return Boolean(url && !url.endsWith('.txt'));
}

function pauseNarration(): void {
    clearNarrationStartTimer();
    const el = pageAudioRef.value;

    if (!el) {
        return;
    }

    el.onended = null;
    el.pause();
}

function allPageVideoEls(): HTMLVideoElement[] {
    if (!flipRoot.value) {
        return [];
    }

    return Array.from(
        flipRoot.value.querySelectorAll('video[data-role="story-page-video"]'),
    ) as HTMLVideoElement[];
}

function pausePageVideos(): void {
    for (const video of allPageVideoEls()) {
        video.pause();
    }
}

function setVideoButtonState(pageUuid: string, playing: boolean): void {
    if (!flipRoot.value) {
        return;
    }

    const selector = `button[data-action="toggle-page-video"][data-page-uuid="${pageUuid}"]`;
    const buttons = Array.from(flipRoot.value.querySelectorAll(selector)) as HTMLButtonElement[];

    if (buttons.length === 0) {
        return;
    }

    for (const button of buttons) {
        if (playing) {
            // Hide the overlay while the video is playing — let the video run unobstructed
            button.style.opacity = '0';
            button.style.pointerEvents = 'none';
        } else {
            // Show the play-circle overlay when paused or ended
            button.style.opacity = '1';
            button.style.pointerEvents = '';
        }
    }
}

function bindVideoOverlayEvents(): void {
    for (const video of allPageVideoEls()) {
        const pageUuid = video.getAttribute('data-page-uuid');
        if (!pageUuid || video.dataset.overlayBound === '1') {
            continue;
        }

        video.dataset.overlayBound = '1';
        video.addEventListener('play', () => setVideoButtonState(pageUuid, true));
        video.addEventListener('pause', () => setVideoButtonState(pageUuid, false));
        video.addEventListener('ended', () => {
            setVideoButtonState(pageUuid, false);

            if (settings.videoPlaybackMode === 'auto' && settings.autoAdvance === 'off') {
                const view = getCurrentView();
                if (!viewShowsEndOfBook(view)) {
                    window.setTimeout(() => nextPage(), 260);
                }
            }
        });
    }
}

function playVideoByPageUuid(pageUuid: string): void {
    if (!flipRoot.value) {
        return;
    }

    const selector = `video[data-role="story-page-video"][data-page-uuid="${pageUuid}"]`;
    const target = flipRoot.value.querySelector(selector) as HTMLVideoElement | null;

    if (!target) {
        return;
    }

    for (const video of allPageVideoEls()) {
        if (video !== target) {
            video.pause();
            video.currentTime = 0;
            const otherUuid = video.getAttribute('data-page-uuid');
            if (otherUuid) {
                setVideoButtonState(otherUuid, false);
            }
        }
    }

    const playAttempt = target.play();
    if (playAttempt && typeof playAttempt.then === 'function') {
        void playAttempt
            .then(() => setVideoButtonState(pageUuid, true))
            .catch(() => {
                // Autoplay can be blocked by browser policy; user can use manual play button.
                setVideoButtonState(pageUuid, false);
            });
    }
}

function playVideoForSpread(view: number[]): void {
    if (settings.videoPlaybackMode !== 'auto') {
        return;
    }

    const indices = storyIndicesInView(view);

    if (indices.length === 0) {
        return;
    }

    const pageUuid = props.pages[indices[0]]?.uuid;
    if (!pageUuid) {
        return;
    }

    const page = props.pages[indices[0]];
    if (!page?.video_url || pageMediaMode(page) !== 'video') {
        return;
    }

    window.setTimeout(() => playVideoByPageUuid(pageUuid), 120);
}

function pageMediaMode(p: FlipbookPage): DefaultMediaMode {
    const override = settings.pageMediaOverrides?.[p.uuid];

    if (override === 'video' || override === 'image') {
        return override;
    }

    return settings.defaultMediaMode;
}

/** Turn.js `turned` passes (event, page, view); tolerate arity/order bugs and fall back to live view. */
function resolveSpreadViewFromTurnEvent(a: unknown, b: unknown): number[] {
    if (Array.isArray(b) && b.length > 0) {
        return b as number[];
    }

    if (Array.isArray(a) && a.length > 0 && typeof (a as number[])[0] === 'number') {
        return a as number[];
    }

    return getCurrentView();
}

function getTurnTotalPages(): number {
    if (!flipRoot.value || !jq) {
        return 0;
    }

    try {
        return jq(flipRoot.value).turn('pages') as unknown as number;
    } catch {
        return 0;
    }
}

function viewShowsEndOfBook(view: number[]): boolean {
    const total = getTurnTotalPages();

    if (total < 1 || view.length === 0) {
        return true;
    }

    return Math.max(...view) >= total;
}

function scheduleTimerAdvance(view: number[]): void {
    clearAdvanceTimer();

    if (settings.autoAdvance !== 'timer' || !ready.value) {
        return;
    }

    if (viewShowsEndOfBook(view)) {
        return;
    }

    const delay = Math.max(2, settings.timerDelaySec) * 1000;
    advanceTimer = window.setTimeout(() => {
        advanceTimer = null;
        nextPage();
    }, delay);
}

function maybeAdvanceAfterAudio(): void {
    if (settings.autoAdvance !== 'afterAudio' || !ready.value) {
        return;
    }

    const view = getCurrentView();

    if (viewShowsEndOfBook(view)) {
        return;
    }

    window.setTimeout(() => nextPage(), 280);
}

function playStoryIndex(idx: number, onDone?: () => void): void {
    const el = pageAudioRef.value;
    const notifyDone = (): void => {
        window.setTimeout(() => onDone?.(), 0);
    };

    if (!el || idx < 0 || idx >= props.pages.length) {
        notifyDone();

        return;
    }

    const url = props.pages[idx]?.audio_url;
    clearNarrationStartTimer();
    el.onended = null;

    if (!isPlayableAudioUrl(url)) {
        notifyDone();

        return;
    }

    const urlStr = url as string;
    /* Defer so we are off the prior `ended` stack; load() helps the next clip; micro-delay aids chained autoplay. */
    narrationStartTimer = window.setTimeout(() => {
        narrationStartTimer = null;
        el.onended = () => {
            el.onended = null;
            notifyDone();
        };
        el.src = urlStr;
        el.load();
        void el.play().catch(() => {
            el.onended = null;
            narrationStartTimer = window.setTimeout(() => {
                narrationStartTimer = null;
                el.onended = () => {
                    el.onended = null;
                    notifyDone();
                };
                void el.play().catch(() => {
                    el.onended = null;
                    notifyDone();
                });
            }, 45);
        });
    }, 0);
}

/** Play left-to-right narration for every story page currently visible (spread). */
function playSpreadNarration(view: number[]): void {
    if (!props.playAudioOnFlip || !settings.audioOnFlip) {
        return;
    }

    if (viewIsOnlyGameSheets(view)) {
        return;
    }

    pauseNarration();
    const indices = storyIndicesInView(view);

    if (indices.length === 0) {
        return;
    }

    if (settings.spreadAudio === 'first') {
        playStoryIndex(indices[0], () => maybeAdvanceAfterAudio());

        return;
    }

    let i = 0;
    const step = (): void => {
        if (i >= indices.length) {
            maybeAdvanceAfterAudio();

            return;
        }

        playStoryIndex(indices[i], () => {
            i += 1;
            step();
        });
    };
    step();
}

function getCurrentView(): number[] {
    if (!flipRoot.value || !jq) {
        return [];
    }

    try {
        return jq(flipRoot.value).turn('view') as unknown as number[];
    } catch {
        return [];
    }
}

function onTurning(): void {
    clearAdvanceTimer();
    pauseNarration();
    pausePageVideos();
}

function onTurned(_e: unknown, pageOrView?: unknown, viewMaybe?: unknown): void {
    const view = resolveSpreadViewFromTurnEvent(pageOrView ?? [], viewMaybe ?? []);
    updateCurrentContentPageNumbers(view);
    emitVisiblePageUuid(view);
    playSpreadNarration(view);
    bindVideoOverlayEvents();
    playVideoForSpread(view);
    scheduleTimerAdvance(view);
    syncBookHorizontalNudge();
    // Re-mount quiz/game sheets after each page turn
    mountGameApps();
}

function emitVisiblePageUuid(view: number[]): void {
    const indices = storyIndicesInView(view);
    const first = indices.length > 0 ? indices[0] : -1;

    if (first < 0 || first >= props.pages.length) {
        emit('view-page-change', null);

        return;
    }

    emit('view-page-change', props.pages[first]?.uuid ?? null);
}

function bookDimensions() {
    const maxW = Math.min(920, typeof window !== 'undefined' ? window.innerWidth - 40 : 920);
    const w = Math.max(320, maxW);
    const h = Math.round(w * 0.62);

    return { w, h };
}

function syncBookHorizontalNudge(): void {
    if (!flipRoot.value || !jq || !ready.value) {
        bookNudgePx.value = 0;

        return;
    }

    if (settings.display !== 'double') {
        bookNudgePx.value = 0;

        return;
    }

    const { w } = bookDimensions();
    const quarter = w / 4;

    try {
        const view = jq(flipRoot.value).turn('view') as unknown as number[];
        const v0 = view[0] ?? 0;
        const v1 = view[1] ?? 0;

        /* Turn.js: closed front uses [0,1] (only right half); closed back uses [last,0] (only left half). */
        if (v0 === 0 && v1 > 0) {
            bookNudgePx.value = -quarter;
        } else if (v0 > 0 && v1 === 0) {
            bookNudgePx.value = quarter;
        } else {
            bookNudgePx.value = 0;
        }
    } catch {
        bookNudgePx.value = 0;
    }
}

function applyDragFlipInteraction(): void {
    if (!flipRoot.value || !jq || !ready.value) {
        return;
    }

    try {
        // When drag-flip is disabled, block pointer/touch page turning.
        // Programmatic turning (buttons/keyboard handlers) still works.
        jq(flipRoot.value).turn('disable', !settings.dragFlipEnabled);
    } catch {
        /* noop */
    }
}

const scalerStyle = computed(() => ({
    transform: `scale(${settings.bookZoomPercent / 100})`,
    transformOrigin: 'center center',
}));

const currentPageLabel = computed(() => {
    const nums = currentContentPageNumbers.value;

    if (nums.length === 0) {
        return 'Viewing cover or quiz sheet';
    }

    if (nums.length === 1) {
        return `Viewing story page ${nums[0]}`;
    }

    return `Viewing story pages ${nums.join(' and ')}`;
});

const bookNudgeStyle = computed(() => ({
    transform: `translateX(${bookNudgePx.value}px)`,
    transition: 'transform 0.25s ease-out',
}));

async function loadTurn(): Promise<void> {
    const jQuery = (await import('jquery')).default;
    window.jQuery = jQuery;
    window.$ = jQuery;
    jq = jQuery;
    // @ts-expect-error turn.js is a UMD plugin that mutates jQuery at runtime.
    await import('../vendor/turn.js');
}

function reducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

async function initTurn(): Promise<void> {
    if (!flipRoot.value || !jq) {
        return;
    }

    const $root = jq(flipRoot.value);
    $root.off('.pageVideoAction');

    let savedTurnPage: number | null = null;
    try {
        const cur = $root.turn('page') as unknown;
        if (typeof cur === 'number' && !Number.isNaN(cur) && cur > 0) {
            savedTurnPage = cur;
        }
    } catch {
        /* turn not initialized yet */
    }

    try {
        $root.turn('destroy');
    } catch {
        /* */
    }

    unmountGameApps();
    $root.empty();
    await nextTick();

    const { w, h } = bookDimensions();

    const frontStyle = coverHardStyle(props.coverFront ?? null);
    const frontWrap = jq('<div class="hard cover-front cover-hard-realistic" />')
        .css(frontStyle)
        .addClass(coverFrameRootClass(props.coverFront?.frame));

    if (Object.keys(frontStyle).length === 0) {
        frontWrap.addClass('cover-hard-default-leather');
    }

    const frontStack = jq(
        '<div class="cover-hard-stack relative flex h-full min-h-0 flex-col overflow-hidden" />',
    );
    frontStack.append(jq('<div class="cover-hard-frame" aria-hidden="true" />'));
    frontStack.append(jq('<div class="cover-hard-spine cover-hard-spine--front" aria-hidden="true" />'));
    frontStack.append(
        jq(
            '<div class="cover-hard-inner relative z-[1] flex h-full flex-col items-center justify-center px-5 py-8 text-center sm:px-8" />',
        ).append(
            jq(
                '<span class="cover-title capitalize text-xl font-extrabold leading-snug tracking-tight text-balance sm:text-2xl sm:leading-snug" />',
            ).text(props.title),
        ),
    );
    frontWrap.append(frontStack);
    $root.append(frontWrap);
    $root.append(jq('<div class="hard hard-inside hard-endpaper" aria-hidden="true" />'));

    const slots = middleSlots();

    slots.forEach((slot, slotIndex) => {
        if (slot.type === 'content') {
            const p = slot.page;
            const inner = jq(
                '<div class="page-inner page-sheet page-sheet-realistic flex h-full flex-col overflow-hidden bg-card" />',
            );
            const turnPageNumber = FRONT_FIXED_SHEETS + 1 + slotIndex;
            const buttonSideClass = turnPageNumber % 2 === 0 ? 'left-2' : 'right-2';
            const pageBusy =
                Boolean(props.pageVideoBusy?.[p.uuid]) || Boolean((p as FlipbookPage).video_generating);
            const canGenerateThisPage =
                Boolean(props.canGeneratePageVideo) && Boolean(p.image_url) && !pageBusy;

            if (p.image_url) {
                const mediaWrap = jq('<div class="relative min-h-0 flex-1 bg-muted/30" />');
                const showVideo = Boolean(p.video_url) && pageMediaMode(p) === 'video';

                if (showVideo && p.video_url) {
                    const videoEl = jq('<video />')
                        .attr('src', p.video_url)
                        .attr('playsinline', 'true')
                        .attr('preload', 'metadata')
                        .attr('data-role', 'story-page-video')
                        .attr('data-page-uuid', p.uuid)
                        .addClass('story-flipbook-page-img')
                        .css({
                            width: '100%',
                            height: '100%',
                            objectFit: 'contain',
                            backgroundColor: '#000',
                        });

                    mediaWrap.append(videoEl);
                    mediaWrap.append(
                        jq(
                            `<button type="button" class="story-video-play-btn absolute inset-0 z-20 grid place-items-center bg-black/30 backdrop-blur-[1px] transition-all duration-200 hover:bg-black/40" data-action="toggle-page-video" data-page-uuid="${p.uuid}" aria-label="Play video for page ${p.page_number}">` +
                                '<span class="inline-flex size-16 items-center justify-center rounded-full bg-white/95 shadow-2xl ring-4 ring-white/30 transition-transform duration-200 hover:scale-110">' +
                                '<svg class="size-7 translate-x-0.5 text-violet-600" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>' +
                                '</span>' +
                            '</button>',
                        ),
                    );
                } else {
                    mediaWrap.append(
                        jq('<img />')
                            .attr('src', p.image_url)
                            .attr('alt', `Page ${p.page_number}`)
                            .attr('draggable', 'false')
                            .addClass('story-flipbook-page-img')
                            .css({
                                width: '100%',
                                height: '100%',
                                objectFit: 'contain',
                            }),
                    );
                }

                // --- icon action buttons grouped in a floating pill stack ---
                const $actionGroup = jq(
                    `<div class="absolute top-2 ${buttonSideClass} z-20 flex flex-col gap-1" />`,
                );

                if (p.video_url && props.setupMode) {
                    const imgSvg =
                        `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">` +
                        `<rect width="18" height="18" x="3" y="3" rx="2"/>` +
                        `<circle cx="9" cy="9" r="2"/>` +
                        `<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>` +
                        `</svg>`;
                    const vidSvg =
                        `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">` +
                        `<path d="m22 8-6 4 6 4V8z"/>` +
                        `<rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>` +
                        `</svg>`;
                    const mediaIcon = showVideo ? imgSvg : vidSvg;
                    const mediaTitle = showVideo ? 'Switch to image' : 'Switch to video';
                    $actionGroup.append(
                        jq(
                            `<button type="button" ` +
                            `class="inline-flex size-7 items-center justify-center rounded-full border border-border/80 bg-background/95 text-foreground/60 shadow-md backdrop-blur-sm transition hover:bg-background hover:text-foreground active:scale-95" ` +
                            `data-action="toggle-page-media" data-page-uuid="${p.uuid}" title="${mediaTitle}">` +
                            `${mediaIcon}</button>`,
                        ),
                    );
                }

                if (props.showPageVideoAction) {
                    const disabledReason = !props.canGeneratePageVideo
                        ? props.pageVideoActionHint || 'Video generation is unavailable.'
                        : pageBusy
                          ? 'Video is being generated for this page.'
                          : !p.image_url
                            ? 'Generate an image first.'
                            : '';
                    const label = pageBusy
                        ? 'Generating…'
                        : p.video_url
                          ? 'Regenerate video'
                          : 'Generate video';

                    const genSvg =
                        `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">` +
                        `<path d="m22 8-6 4 6 4V8z"/>` +
                        `<rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>` +
                        `</svg>`;
                    const reSvg =
                        `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">` +
                        `<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>` +
                        `<path d="M21 3v5h-5"/>` +
                        `<path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>` +
                        `<path d="M8 16H3v5"/>` +
                        `</svg>`;
                    const busySvg =
                        `<svg class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">` +
                        `<path d="M21 12a9 9 0 1 1-6.219-8.56"/>` +
                        `</svg>`;
                    const btnIcon = pageBusy ? busySvg : p.video_url ? reSvg : genSvg;

                    const $vBtn = jq(
                        `<button type="button" ` +
                        `class="inline-flex size-7 items-center justify-center rounded-full border border-violet-300/80 bg-violet-50/95 text-violet-600 shadow-md backdrop-blur-sm transition hover:bg-violet-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50" ` +
                        `data-action="generate-page-video" data-page-uuid="${p.uuid}" title="${disabledReason || label}">` +
                        `${btnIcon}</button>`,
                    );
                    if (!canGenerateThisPage) {
                        $vBtn.attr('disabled', 'true');
                    }
                    $actionGroup.append($vBtn);
                }

                if ($actionGroup.children().length) {
                    mediaWrap.append($actionGroup);
                }

                inner.append(mediaWrap);
            }

            inner.append(
                jq(
                    '<div class="page-sheet-foot shrink-0 border-t border-border/55 p-3 text-xs leading-snug sm:p-4 sm:text-sm" />',
                ).append(jq('<p class="text-foreground/90" />').text(p.text_content ?? '')),
            );
            $root.append(inner);
        } else {
            const mount = jq(
                '<div class="page-inner page-sheet page-sheet-realistic story-game-sheet flex h-full flex-col overflow-hidden bg-card" />',
            );
            const holder = jq('<div class="game-sheet-mount min-h-0 flex-1 w-full" />').attr(
                'data-page-uuid',
                slot.page.uuid,
            );
            mount.append(holder);
            $root.append(mount);
        }
    });

    $root.append(jq('<div class="hard hard-inside-back hard-endpaper" aria-hidden="true" />'));
    const backStyle = coverHardStyle(props.coverBack ?? null);
    const backWrap = jq('<div class="hard cover-back cover-hard-realistic" />')
        .css(backStyle)
        .addClass(coverFrameRootClass(props.coverBack?.frame));

    if (Object.keys(backStyle).length === 0) {
        backWrap.addClass('cover-hard-default-leather');
    }

    const backStack = jq(
        '<div class="cover-hard-stack relative flex h-full min-h-0 flex-col overflow-hidden" />',
    );
    backStack.append(jq('<div class="cover-hard-frame cover-hard-frame--back" aria-hidden="true" />'));
    backStack.append(jq('<div class="cover-hard-spine cover-hard-spine--back" aria-hidden="true" />'));
    backStack.append(
        jq(
            '<div class="cover-hard-inner cover-hard-inner--back relative z-[1] flex h-full flex-col items-center justify-center px-5 py-8 text-center text-sm sm:px-8" />',
        ).append(
            jq('<span class="cover-end-title font-medium tracking-wide opacity-95" />').text('The end'),
        ),
    );
    backWrap.append(backStack);
    $root.append(backWrap);

    const useAcceleration = settings.acceleration && !reducedMotion();

    const totalRenderedPages = $root.children().length;

    $root.turn({
        width: w,
        height: h,
        autoCenter: true,
        gradients: settings.gradients,
        duration: settings.flipDuration,
        acceleration: useAcceleration,
        display: settings.display,
        elevation: settings.elevation,
        corners: settings.dragFlipEnabled ? settings.corners : 'none',
        pages: totalRenderedPages,
        when: {
            turning: onTurning,
            turned: onTurned,
        },
    });

    if (savedTurnPage !== null && savedTurnPage >= 1 && savedTurnPage <= totalRenderedPages) {
        try {
            $root.turn('page', savedTurnPage);
        } catch {
            /* */
        }
    }

    ready.value = true;
    applyDragFlipInteraction();

    $root.on('pointerdown.pageVideoAction mousedown.pageVideoAction touchstart.pageVideoAction', '[data-action="generate-page-video"]', (e: unknown) => {
        (e as Event).stopPropagation();
    });
    $root.on('pointerdown.pageMediaToggle mousedown.pageMediaToggle touchstart.pageMediaToggle', '[data-action="toggle-page-media"]', (e: unknown) => {
        (e as Event).stopPropagation();
    });
    $root.on('click.pageVideoAction', '[data-action="generate-page-video"]', (e: unknown) => {
        (e as Event).preventDefault();
        (e as Event).stopPropagation();
        const target = e.currentTarget as HTMLElement | null;
        const pageUuid = target?.getAttribute('data-page-uuid');

        if (!pageUuid) {
            return;
        }

        emit('generate-page-video', pageUuid);
    });
    $root.on('click.pageMediaToggle', '[data-action="toggle-page-media"]', (e: unknown) => {
        (e as Event).preventDefault();
        (e as Event).stopPropagation();

        const target = e.currentTarget as HTMLElement | null;
        const pageUuid = target?.getAttribute('data-page-uuid');

        if (!pageUuid) {
            return;
        }

        const current = settings.pageMediaOverrides?.[pageUuid] ?? settings.defaultMediaMode;
        const next: DefaultMediaMode = current === 'video' ? 'image' : 'video';
        settings.pageMediaOverrides = {
            ...(settings.pageMediaOverrides ?? {}),
            [pageUuid]: next,
        };
        scheduleRebuildTurn();
    });
    $root.on('pointerdown.pageVideoToggle mousedown.pageVideoToggle touchstart.pageVideoToggle', '[data-action="toggle-page-video"]', (e: unknown) => {
        (e as Event).stopPropagation();
    });
    $root.on('click.pageVideoToggle', '[data-action="toggle-page-video"]', (e: unknown) => {
        (e as Event).preventDefault();
        (e as Event).stopPropagation();

        const target = e.currentTarget as HTMLElement | null;
        const pageUuid = target?.getAttribute('data-page-uuid');

        if (!pageUuid || !flipRoot.value) {
            return;
        }

        const selector = `video[data-role="story-page-video"][data-page-uuid="${pageUuid}"]`;
        const video = flipRoot.value.querySelector(selector) as HTMLVideoElement | null;

        if (!video) {
            return;
        }

        if (video.paused) {
            playVideoByPageUuid(pageUuid);
        } else {
            video.pause();
            setVideoButtonState(pageUuid, false);
        }
    });

    bindVideoOverlayEvents();
    const currentView = $root.turn('view') as unknown as number[];
    updateCurrentContentPageNumbers(currentView);
    emitVisiblePageUuid(currentView);
    playSpreadNarration(currentView);
    playVideoForSpread(currentView);
    scheduleTimerAdvance(currentView);

    await nextTick();
    mountGameApps();
    syncBookHorizontalNudge();
}

function mountOneGameApp(el: Element): void {
    const uuid = el.getAttribute('data-page-uuid');
    if (!uuid || gameMountedApps.has(uuid)) {
        return;
    }
    const page = props.pages.find((p) => p.uuid === uuid);
    if (!page) {
        return;
    }
    /* Ensure the element is still connected to the live DOM before mounting */
    if (!el.isConnected) {
        return;
    }
    const app = createApp({
        setup() {
            return () =>
                h(StoryQuizSheet, {
                    storyUuid: props.storyUuid,
                    pageUuid: uuid,
                    questions: normalizeQuiz(page.quiz_questions),
                    editable: props.setupMode,
                });
        },
    });
    app.mount(el);
    gameMountedApps.set(uuid, app);
}

function unmountGameAppByUuid(uuid: string): void {
    const app = gameMountedApps.get(uuid);
    if (!app) {
        return;
    }
    try {
        app.unmount();
    } catch {
        /* already detached */
    }
    gameMountedApps.delete(uuid);
}

function mountGameApps(): void {
    if (!flipRoot.value) {
        return;
    }

    /* Mount any game-sheet-mount nodes now in the DOM */
    flipRoot.value.querySelectorAll('.game-sheet-mount').forEach((el) => mountOneGameApp(el));

    /* Un-mount apps whose elements have been removed by Turn.js's sliding DOM window */
    gameMountedApps.forEach((_app, uuid) => {
        const still = flipRoot.value?.querySelector(`.game-sheet-mount[data-page-uuid="${uuid}"]`);
        if (!still || !still.isConnected) {
            unmountGameAppByUuid(uuid);
        }
    });

    /* Watch for Turn.js adding new game-sheet-mount nodes (lazy DOM window) */
    if (!gameMountObserver && flipRoot.value) {
        gameMountObserver = new MutationObserver(() => {
            if (!flipRoot.value) {
                return;
            }
            flipRoot.value.querySelectorAll('.game-sheet-mount').forEach((el) => mountOneGameApp(el));
            gameMountedApps.forEach((_app, uuid) => {
                const still = flipRoot.value?.querySelector(`.game-sheet-mount[data-page-uuid="${uuid}"]`);
                if (!still || !still.isConnected) {
                    unmountGameAppByUuid(uuid);
                }
            });
        });
        gameMountObserver.observe(flipRoot.value, { childList: true, subtree: true });
    }
}

function resizeTurn(): void {
    if (!flipRoot.value || !jq || !ready.value) {
        return;
    }

    const { w, h } = bookDimensions();

    try {
        jq(flipRoot.value).turn('size', w, h);
    } catch {
        /* */
    }
}

function prevPage(): void {
    if (!flipRoot.value || !jq) {
        return;
    }

    try {
        jq(flipRoot.value).turn('previous');
    } catch {
        /* */
    }
}

function nextPage(): void {
    if (!flipRoot.value || !jq) {
        return;
    }

    try {
        jq(flipRoot.value).turn('next');
    } catch {
        /* */
    }
}

function onResize(): void {
    resizeTurn();
    syncBookHorizontalNudge();
}

let rebuildTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleRebuildTurn(): void {
    if (rebuildTimer !== null) {
        clearTimeout(rebuildTimer);
    }

    rebuildTimer = window.setTimeout(() => {
        rebuildTimer = null;
        ready.value = false;
        void initTurn();
    }, 320);
}

watch(
    () => [
        settings.display,
        settings.flipDuration,
        settings.gradients,
        settings.acceleration,
        settings.elevation,
        settings.defaultMediaMode,
    ],
    () => {
        if (!jq || !flipRoot.value) {
            return;
        }

        scheduleRebuildTurn();
    },
);

watch(
    () => settings.dragFlipEnabled,
    () => {
        applyDragFlipInteraction();
    },
);

watch(
    () => settings.pageMediaOverrides,
    () => {
        if (!jq || !flipRoot.value) {
            return;
        }

        scheduleRebuildTurn();
    },
    { deep: true },
);

watch(
    () => [
        props.pages,
        props.pageVideoBusy,
        props.canGeneratePageVideo,
        props.showPageVideoAction,
        props.gameplayEnabled,
        props.coverFront,
        props.coverBack,
        props.setupMode,
        props.flipSettings,
    ],
    () => {
        if (!jq || !flipRoot.value) {
            return;
        }

        scheduleRebuildTurn();
    },
    { deep: true },
);

function onKeyDown(e: KeyboardEvent): void {
    if (!ready.value) {
        return;
    }

    const target = e.target as HTMLElement | null;

    if (target?.closest('input, textarea, select, [contenteditable="true"]')) {
        return;
    }

    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevPage();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextPage();
    }
}

onMounted(async () => {
    initializeFlipUiSettings();
    await loadTurn();
    await nextTick();
    await initTurn();
    window.addEventListener('resize', onResize);
    window.addEventListener('keydown', onKeyDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    window.removeEventListener('keydown', onKeyDown);
    clearAdvanceTimer();
    unmountGameApps();

    if (rebuildTimer !== null) {
        clearTimeout(rebuildTimer);
    }

    if (persistFlipTimer !== null) {
        clearTimeout(persistFlipTimer);
    }

    pauseNarration();

    if (flipRoot.value && jq) {
        try {
            jq(flipRoot.value).turn('destroy');
        } catch {
            /* */
        }
    }

    ready.value = false;
});
</script>

<template>
    <div class="story-flipbook-container w-full">
        <div v-if="setupMode" class="grid w-full items-start gap-5 xl:grid-cols-[320px_1fr]">
            <StoryFlipbookSetupPanel
                :settings="settings"
                :play-audio-on-flip="playAudioOnFlip"
                :narration-unavailable-hint="narrationUnavailableHint"
                :include-quiz="includeQuiz"
                :has-quiz-pages="hasQuizPages"
                :story-uuid="storyUuid"
                :setup-mode="setupMode"
                :gameplay-enabled-local="gameplayEnabledLocal"
                :gameplay-toggle-busy="gameplayToggleBusy"
                :show-video-media-settings="showVideoMediaSettings"
                @set-gameplay-enabled="setGameplayEnabled"
            >
                <slot name="setup-extra" />
            </StoryFlipbookSetupPanel>

            <section class="relative z-0 flex min-w-0 flex-col items-center gap-4">
                <div class="absolute top-2 left-2 z-30">
                    <slot name="book-top-left" />
                </div>
                <p class="text-muted-foreground max-w-xl text-center text-xs">
                    Drag the <strong>page corners</strong> to flip (mouse or touch). Keyboard:
                    <kbd class="bg-muted rounded px-1">←</kbd> /
                    <kbd class="bg-muted rounded px-1">→</kbd>.
                </p>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <Button type="button" variant="outline" size="sm" :disabled="!ready" @click="prevPage">
                        <ChevronLeft class="mr-1 size-4" />
                        Previous
                    </Button>
                    <Button type="button" variant="outline" size="sm" :disabled="!ready" @click="nextPage">
                        Next
                        <ChevronRight class="ml-1 size-4" />
                    </Button>
                </div>

                <div class="flip-stage perspective-desk w-full max-w-[min(100%,980px)]">
                    <div class="book-ambient" aria-hidden="true" />
                    <div class="book-drop-shadow">
                        <div class="book-horizontal-nudge" :style="bookNudgeStyle">
                            <div class="book-scaler" :style="scalerStyle">
                                <div ref="flipRoot" class="story-flipbook touch-none overflow-hidden rounded-lg" />
                            </div>
                        </div>
                    </div>
                    <div class="book-floor-shadow" aria-hidden="true" />
                </div>

                <p class="text-muted-foreground text-xs">
                    {{ currentPageLabel }}
                </p>
            </section>
        </div>

        <div v-else class="relative flex w-full flex-col items-center gap-4">
            <div class="absolute top-2 left-2 z-30">
                <slot name="book-top-left" />
            </div>
            <p class="text-muted-foreground max-w-md text-center text-xs">
                Drag the page corners or use
                <kbd class="bg-muted rounded px-1">←</kbd>
                /
                <kbd class="bg-muted rounded px-1">→</kbd>
                to turn the page.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-2">
                <Button type="button" variant="outline" size="sm" :disabled="!ready" @click="prevPage">
                    <ChevronLeft class="mr-1 size-4" />
                    Previous
                </Button>
                <Button type="button" variant="outline" size="sm" :disabled="!ready" @click="nextPage">
                    Next
                    <ChevronRight class="ml-1 size-4" />
                </Button>
            </div>

            <div class="flip-stage perspective-desk w-full max-w-[min(100%,980px)]">
                <div class="book-ambient" aria-hidden="true" />
                <div class="book-drop-shadow">
                    <div class="book-horizontal-nudge" :style="bookNudgeStyle">
                        <div class="book-scaler" :style="scalerStyle">
                            <div ref="flipRoot" class="story-flipbook touch-none overflow-hidden rounded-lg" />
                        </div>
                    </div>
                </div>
                <div class="book-floor-shadow" aria-hidden="true" />
            </div>

            <p class="text-muted-foreground text-xs">
                {{ currentPageLabel }}
            </p>
        </div>

        <audio ref="pageAudioRef" class="hidden" playsinline />
    </div>
</template>

<style>
/* Stage: depth + realistic outer shadows (Turn adds internal curl shadows) */
.perspective-desk {
    perspective: 2200px;
    perspective-origin: 50% 42%;
    padding: 1.5rem 0 2.5rem;
}

.book-ambient {
    pointer-events: none;
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 0;
    width: min(96%, 880px);
    height: 120px;
    transform: translate(-50%, -40%);
    border-radius: 50%;
    background: radial-gradient(
        ellipse at center,
        color-mix(in oklab, var(--primary) 18%, transparent) 0%,
        transparent 70%
    );
    opacity: 0.55;
    filter: blur(20px);
}

.flip-stage {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 0;
}

.book-drop-shadow {
    position: relative;
    z-index: 0;
    filter: drop-shadow(0 28px 40px rgb(0 0 0 / 0.28)) drop-shadow(0 10px 16px rgb(0 0 0 / 0.12));
}

.dark .book-drop-shadow {
    filter: drop-shadow(0 32px 56px rgb(0 0 0 / 0.55)) drop-shadow(0 14px 22px rgb(0 0 0 / 0.35));
}

.book-horizontal-nudge {
    position: relative;
    z-index: 0;
    will-change: transform;
}

.book-scaler {
    transition: transform 0.25s ease-out;
    will-change: transform;
}

.book-floor-shadow {
    pointer-events: none;
    margin-top: -0.5rem;
    z-index: 0;
    height: 28px;
    width: min(78%, 640px);
    border-radius: 50%;
    background: radial-gradient(ellipse at center, rgb(0 0 0 / 0.22) 0%, transparent 72%);
    filter: blur(8px);
    opacity: 0.85;
}

.dark .book-floor-shadow {
    background: radial-gradient(ellipse at center, rgb(0 0 0 / 0.45) 0%, transparent 72%);
}

.story-flipbook {
    transform: rotateX(4deg);
    transform-style: preserve-3d;
    backface-visibility: hidden;
}

.story-flipbook .turn-page {
    background-color: var(--card);
    color: var(--card-foreground);
}

/* Let Turn.js handle drag-to-flip; avoid browser image drag / selection on illustrations */
.story-flipbook-page-img {
    -webkit-user-drag: none;
    user-select: none;
    pointer-events: none;
}

.story-flipbook .hard {
    background: linear-gradient(
        135deg,
        var(--muted) 0%,
        var(--card) 45%,
        color-mix(in oklab, var(--muted) 85%, transparent) 100%
    );
    color: var(--foreground);
}

/* Depth on every sheet (content, game, and inner hard pages), not only outer covers */
.story-flipbook .page-sheet,
.story-flipbook .hard {
    box-shadow:
        inset 0 0 0 1px rgb(0 0 0 / 0.04),
        inset 0 2px 28px rgb(0 0 0 / 0.07),
        inset 0 1px 0 rgb(255 255 255 / 0.05);
}

.dark .story-flipbook .page-sheet,
.dark .story-flipbook .hard {
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.05),
        inset 0 2px 32px rgb(0 0 0 / 0.32),
        inset 0 1px 0 rgb(255 255 255 / 0.03);
}

/* Inner story pages: paper depth, margin frame, subtle grain (matches cover polish) */
.story-flipbook .page-inner.page-sheet.page-sheet-realistic {
    position: relative;
    isolation: isolate;
    background-color: var(--card);
    background-image:
        radial-gradient(ellipse 92% 68% at 50% 10%, rgb(255 255 255 / 0.1), transparent 54%),
        radial-gradient(ellipse 72% 52% at 86% 88%, rgb(0 0 0 / 0.045), transparent 52%),
        radial-gradient(ellipse 62% 48% at 7% 78%, rgb(0 0 0 / 0.04), transparent 50%);
    box-shadow:
        inset 0 0 0 1px rgb(0 0 0 / 0.07),
        inset 0 0 0 5px rgb(0 0 0 / 0.028),
        inset 0 0 0 6px rgb(255 255 255 / 0.05),
        inset 0 0 0 7px rgb(0 0 0 / 0.02),
        inset 0 0 52px 18px rgb(0 0 0 / 0.065),
        inset 0 18px 40px rgb(0 0 0 / 0.05),
        inset 0 1px 0 rgb(255 255 255 / 0.48);
}

.dark .story-flipbook .page-inner.page-sheet.page-sheet-realistic {
    background-image:
        radial-gradient(ellipse 92% 68% at 50% 10%, rgb(255 255 255 / 0.05), transparent 54%),
        radial-gradient(ellipse 72% 52% at 86% 88%, rgb(0 0 0 / 0.32), transparent 52%),
        radial-gradient(ellipse 62% 48% at 7% 78%, rgb(0 0 0 / 0.28), transparent 50%);
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.06),
        inset 0 0 0 5px rgb(0 0 0 / 0.22),
        inset 0 0 0 6px rgb(255 255 255 / 0.03),
        inset 0 0 0 7px rgb(0 0 0 / 0.12),
        inset 0 0 60px 22px rgb(0 0 0 / 0.38),
        inset 0 16px 36px rgb(0 0 0 / 0.34),
        inset 0 1px 0 rgb(255 255 255 / 0.04);
}

.story-flipbook .page-inner.page-sheet.page-sheet-realistic::before {
    content: '';
    position: absolute;
    inset: 10px 12px 12px 12px;
    pointer-events: none;
    z-index: 0;
    border-radius: 2px;
    box-shadow:
        inset 0 0 0 1px rgb(0 0 0 / 0.08),
        inset 0 0 0 4px rgb(0 0 0 / 0.025),
        inset 0 0 32px rgb(0 0 0 / 0.07),
        inset 0 1px 0 rgb(255 255 255 / 0.14);
}

.dark .story-flipbook .page-inner.page-sheet.page-sheet-realistic::before {
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.07),
        inset 0 0 0 4px rgb(0 0 0 / 0.18),
        inset 0 0 36px rgb(0 0 0 / 0.34),
        inset 0 1px 0 rgb(255 255 255 / 0.05);
}

.story-flipbook .page-inner.page-sheet.page-sheet-realistic::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 2;
    border-radius: inherit;
    opacity: 0.2;
    mix-blend-mode: multiply;
    background-image:
        repeating-linear-gradient(
            0deg,
            transparent 0,
            transparent 1px,
            rgb(0 0 0 / 0.02) 1px,
            rgb(0 0 0 / 0.02) 2px
        ),
        repeating-linear-gradient(
            98deg,
            transparent 0,
            transparent 3px,
            rgb(0 0 0 / 0.016) 3px,
            rgb(0 0 0 / 0.016) 4px
        );
}

.dark .story-flipbook .page-inner.page-sheet.page-sheet-realistic::after {
    opacity: 0.38;
    mix-blend-mode: soft-light;
}

.story-flipbook .page-inner.page-sheet.page-sheet-realistic > * {
    position: relative;
    z-index: 3;
}

.story-flipbook .page-sheet-foot {
    border-top-color: color-mix(in oklab, var(--border) 72%, transparent);
    background: linear-gradient(180deg, rgb(0 0 0 / 0.025), transparent 48%);
    box-shadow:
        inset 0 1px 0 rgb(255 255 255 / 0.2),
        inset 0 10px 36px rgb(0 0 0 / 0.035);
}

.dark .story-flipbook .page-sheet-foot {
    border-top-color: color-mix(in oklab, var(--border) 55%, transparent);
    background: linear-gradient(180deg, rgb(0 0 0 / 0.22), transparent 52%);
    box-shadow:
        inset 0 1px 0 rgb(255 255 255 / 0.06),
        inset 0 10px 36px rgb(0 0 0 / 0.18);
}

/* Inner hard leaves (endpapers beside the block): cream paper, light tooth */
.story-flipbook .hard.hard-endpaper {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(ellipse 100% 90% at 50% 0%, rgb(255 255 255 / 0.14), transparent 58%),
        linear-gradient(
            148deg,
            color-mix(in oklab, var(--card) 90%, var(--muted)) 0%,
            var(--card) 100%
        );
    box-shadow:
        inset 0 0 0 1px rgb(0 0 0 / 0.06),
        inset 0 0 52px 18px rgb(0 0 0 / 0.07),
        inset 0 18px 40px rgb(0 0 0 / 0.05),
        inset 0 1px 0 rgb(255 255 255 / 0.45);
}

.story-flipbook .hard.hard-endpaper::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0.45;
    background-image: repeating-linear-gradient(
        180deg,
        rgb(0 0 0 / 0.018) 0px,
        transparent 1px,
        transparent 2px
    );
}

.dark .story-flipbook .hard.hard-endpaper {
    background:
        radial-gradient(ellipse 100% 90% at 50% 0%, rgb(255 255 255 / 0.04), transparent 58%),
        linear-gradient(
            148deg,
            color-mix(in oklab, var(--card) 86%, var(--muted)) 0%,
            var(--card) 100%
        );
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.05),
        inset 0 0 60px 22px rgb(0 0 0 / 0.38),
        inset 0 16px 36px rgb(0 0 0 / 0.34),
        inset 0 1px 0 rgb(255 255 255 / 0.04);
}

.dark .story-flipbook .hard.hard-endpaper::before {
    opacity: 0.35;
    background-image: repeating-linear-gradient(
        180deg,
        rgb(255 255 255 / 0.02) 0px,
        transparent 1px,
        transparent 2px
    );
}

.story-flipbook .cover-title {
    max-width: min(100%, 16rem);
    margin-inline: auto;
    hyphens: auto;
    overflow-wrap: anywhere;
    text-shadow:
        0 0 1px rgb(0 0 0 / 0.55),
        0 2px 4px rgb(0 0 0 / 0.45),
        0 6px 24px rgb(0 0 0 / 0.38);
}

/* Hard covers: leather-like depth, ornate frame, recessed panel, spine ribs (front + back) */
.story-flipbook .hard.cover-hard-realistic {
    position: relative;
    overflow: hidden;
    box-shadow:
        0 1px 0 rgb(255 255 255 / 0.07),
        0 16px 36px rgb(0 0 0 / 0.34),
        0 0 0 1px rgb(0 0 0 / 0.2),
        inset 0 0 0 1px rgb(255 255 255 / 0.06),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
}

.story-flipbook .cover-front.cover-hard-realistic {
    border-radius: 4px 8px 8px 4px;
}

.story-flipbook .cover-back.cover-hard-realistic {
    border-radius: 8px 4px 4px 8px;
}

.dark .story-flipbook .hard.cover-hard-realistic {
    box-shadow:
        0 1px 0 rgb(255 255 255 / 0.04),
        0 20px 48px rgb(0 0 0 / 0.55),
        0 0 0 1px rgb(0 0 0 / 0.45),
        inset 0 0 0 1px rgb(255 255 255 / 0.05),
        inset 0 1px 0 rgb(255 255 255 / 0.04);
}

.story-flipbook .cover-hard-default-leather {
    background:
        radial-gradient(ellipse 130% 95% at 50% 100%, rgb(0 0 0 / 0.44), transparent 56%),
        radial-gradient(ellipse 75% 55% at 12% 8%, rgb(255 255 255 / 0.1), transparent 50%),
        radial-gradient(ellipse 55% 65% at 88% 90%, rgb(0 0 0 / 0.32), transparent 52%),
        linear-gradient(148deg, #24160f 0%, #4a301f 36%, #3b2618 64%, #1c1009 100%);
    color: rgb(245 240 232);
}

.dark .story-flipbook .cover-hard-default-leather {
    background:
        radial-gradient(ellipse 130% 95% at 50% 100%, rgb(0 0 0 / 0.5), transparent 56%),
        radial-gradient(ellipse 75% 55% at 12% 8%, rgb(255 255 255 / 0.06), transparent 50%),
        radial-gradient(ellipse 55% 65% at 88% 90%, rgb(0 0 0 / 0.4), transparent 52%),
        linear-gradient(148deg, #1a0f0a 0%, #352218 38%, #2a1a12 62%, #120a06 100%);
    color: rgb(235 228 218);
}

.story-flipbook .cover-hard-stack {
    border-radius: inherit;
}

/* Frame layer: neutral base; each .cover-frame-* template fills in the full look */
.story-flipbook .cover-hard-frame {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    border-radius: inherit;
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.03);
}

.story-flipbook .cover-hard-frame::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: inherit;
    box-shadow: none;
}

.story-flipbook .cover-hard-frame::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: inherit;
    opacity: 0;
    mix-blend-mode: overlay;
    background: none;
}

/* ─── Classic leather (warm antique, gold + tooled grain) ─── */
.story-flipbook .cover-frame-classic-leather .cover-hard-frame {
    box-shadow:
        inset 0 0 0 2px rgb(212 175 55 / 0.28),
        inset 0 0 0 6px rgb(60 35 20 / 0.35),
        inset 0 0 0 9px rgb(212 175 55 / 0.12),
        inset 0 0 0 11px rgb(0 0 0 / 0.2),
        inset 0 0 56px 20px rgb(0 0 0 / 0.48),
        inset 0 6px 22px rgb(255 255 255 / 0.06);
}

.story-flipbook .cover-frame-classic-leather .cover-hard-frame::before {
    inset: 8% 7% 10% 7%;
    border-radius: 3px;
    box-shadow:
        inset 0 0 0 1px rgb(0 0 0 / 0.52),
        inset 0 10px 34px rgb(0 0 0 / 0.5),
        inset 0 2px 0 rgb(255 255 255 / 0.07);
}

.story-flipbook .cover-frame-classic-leather .cover-hard-frame::after {
    opacity: 0.14;
    mix-blend-mode: overlay;
    background-image: repeating-linear-gradient(
        -14deg,
        transparent 0,
        transparent 2px,
        rgb(40 20 10 / 0.06) 2px,
        rgb(40 20 10 / 0.06) 3px
    );
}

.story-flipbook .cover-frame-classic-leather .cover-hard-frame--back::before {
    inset: 8% 7% 10% 7%;
}

.story-flipbook .cover-frame-classic-leather .cover-hard-spine {
    width: min(5.5%, 22px);
    background: repeating-linear-gradient(
        180deg,
        rgb(30 15 8 / 0.45) 0px,
        rgb(30 15 8 / 0.45) 3px,
        rgb(255 230 200 / 0.07) 3px,
        rgb(255 230 200 / 0.07) 7px
    );
    box-shadow: inset -5px 0 11px rgb(0 0 0 / 0.45);
}

/* ─── Minimal art book (silver hairline, huge calm field) ─── */
.story-flipbook .cover-frame-minimal-gilt .cover-hard-frame {
    box-shadow:
        inset 0 0 0 1px rgb(220 220 228 / 0.55),
        inset 0 0 0 3px rgb(0 0 0 / 0.06),
        inset 0 0 0 4px rgb(255 255 255 / 0.04),
        inset 0 0 24px 10px rgb(0 0 0 / 0.12);
}

.story-flipbook .cover-frame-minimal-gilt .cover-hard-frame::before {
    inset: 4.5% 4.5% 5.5% 4.5%;
    border-radius: 2px;
    box-shadow:
        inset 0 0 0 1px rgb(0 0 0 / 0.08),
        inset 0 2px 12px rgb(0 0 0 / 0.06),
        inset 0 1px 0 rgb(255 255 255 / 0.25);
}

.story-flipbook .cover-frame-minimal-gilt .cover-hard-frame::after {
    opacity: 0.35;
    mix-blend-mode: soft-light;
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 14px,
        rgb(255 255 255 / 0.04) 14px,
        rgb(255 255 255 / 0.04) 15px
    );
}

.dark .story-flipbook .cover-frame-minimal-gilt .cover-hard-frame {
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.14),
        inset 0 0 0 3px rgb(0 0 0 / 0.3),
        inset 0 0 28px 12px rgb(0 0 0 / 0.35);
}

.dark .story-flipbook .cover-frame-minimal-gilt .cover-hard-frame::before {
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.06),
        inset 0 2px 14px rgb(0 0 0 / 0.45),
        inset 0 1px 0 rgb(255 255 255 / 0.05);
}

.dark .story-flipbook .cover-frame-minimal-gilt .cover-hard-frame::after {
    opacity: 0.2;
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 12px,
        rgb(0 0 0 / 0.15) 12px,
        rgb(0 0 0 / 0.15) 13px
    );
}

/* ─── Industrial metal (stepped gunmetal bands, fine machine grid) ─── */
.story-flipbook .cover-frame-modern-bevel .cover-hard-frame {
    box-shadow:
        inset 0 0 0 1px rgb(148 163 184 / 0.35),
        inset 0 0 0 3px rgb(15 23 42 / 0.55),
        inset 0 0 0 4px rgb(100 116 139 / 0.2),
        inset 0 0 0 6px rgb(15 23 42 / 0.45),
        inset 0 0 0 7px rgb(226 232 240 / 0.08),
        inset 0 0 40px 18px rgb(0 0 0 / 0.4),
        inset 0 8px 18px rgb(255 255 255 / 0.05);
}

.story-flipbook .cover-frame-modern-bevel .cover-hard-frame::before {
    inset: 7% 6.5% 9% 6.5%;
    border-radius: 1px;
    box-shadow:
        inset 0 0 0 1px rgb(30 41 59 / 0.7),
        inset 0 4px 14px rgb(0 0 0 / 0.4),
        inset 0 1px 0 rgb(148 163 184 / 0.25);
}

.story-flipbook .cover-frame-modern-bevel .cover-hard-frame::after {
    opacity: 0.55;
    mix-blend-mode: soft-light;
    background-image:
        repeating-linear-gradient(
            90deg,
            transparent,
            transparent 5px,
            rgb(15 23 42 / 0.06) 5px,
            rgb(15 23 42 / 0.06) 6px
        ),
        repeating-linear-gradient(
            0deg,
            transparent,
            transparent 5px,
            rgb(15 23 42 / 0.05) 5px,
            rgb(15 23 42 / 0.05) 6px
        );
}

.dark .story-flipbook .cover-frame-modern-bevel .cover-hard-frame {
    box-shadow:
        inset 0 0 0 1px rgb(148 163 184 / 0.22),
        inset 0 0 0 3px rgb(0 0 0 / 0.6),
        inset 0 0 0 5px rgb(71 85 105 / 0.25),
        inset 0 0 0 6px rgb(0 0 0 / 0.5),
        inset 0 0 0 7px rgb(226 232 240 / 0.05),
        inset 0 0 48px 22px rgb(0 0 0 / 0.55),
        inset 0 6px 16px rgb(255 255 255 / 0.04);
}

.dark .story-flipbook .cover-frame-modern-bevel .cover-hard-frame::before {
    box-shadow:
        inset 0 0 0 1px rgb(30 41 59 / 0.85),
        inset 0 4px 18px rgb(0 0 0 / 0.55),
        inset 0 1px 0 rgb(148 163 184 / 0.15);
}

.dark .story-flipbook .cover-frame-modern-bevel .cover-hard-frame::after {
    opacity: 0.35;
}

/* ─── Baroque treasure (brass ladders + corner rosette glow) ─── */
.story-flipbook .cover-frame-ornate-baroque .cover-hard-frame {
    box-shadow:
        inset 0 0 0 2px rgb(196 154 58 / 0.5),
        inset 0 0 0 5px rgb(45 25 12 / 0.55),
        inset 0 0 0 8px rgb(212 175 55 / 0.28),
        inset 0 0 0 11px rgb(20 10 5 / 0.5),
        inset 0 0 0 14px rgb(184 115 51 / 0.22),
        inset 0 0 70px 28px rgb(0 0 0 / 0.55),
        inset 0 8px 24px rgb(255 230 180 / 0.12);
}

.story-flipbook .cover-frame-ornate-baroque .cover-hard-frame::before {
    inset: 12% 10% 14% 10%;
    border-radius: 4px;
    box-shadow:
        inset 0 0 0 2px rgb(0 0 0 / 0.45),
        inset 0 14px 46px rgb(0 0 0 / 0.62),
        inset 0 2px 0 rgb(255 220 160 / 0.09);
}

.story-flipbook .cover-frame-ornate-baroque .cover-hard-frame::after {
    opacity: 1;
    mix-blend-mode: soft-light;
    background-image:
        radial-gradient(ellipse 38% 32% at 10% 8%, rgb(255 210 120 / 0.35), transparent 62%),
        radial-gradient(ellipse 38% 32% at 90% 8%, rgb(255 210 120 / 0.35), transparent 62%),
        radial-gradient(ellipse 35% 30% at 10% 92%, rgb(255 200 100 / 0.22), transparent 58%),
        radial-gradient(ellipse 35% 30% at 90% 92%, rgb(255 200 100 / 0.22), transparent 58%),
        repeating-linear-gradient(
            0deg,
            transparent,
            transparent 3px,
            rgb(80 40 10 / 0.04) 3px,
            rgb(80 40 10 / 0.04) 4px
        );
}

.dark .story-flipbook .cover-frame-ornate-baroque .cover-hard-frame {
    box-shadow:
        inset 0 0 0 2px rgb(196 154 58 / 0.35),
        inset 0 0 0 5px rgb(0 0 0 / 0.65),
        inset 0 0 0 8px rgb(212 175 55 / 0.18),
        inset 0 0 0 11px rgb(0 0 0 / 0.55),
        inset 0 0 0 14px rgb(140 80 40 / 0.2),
        inset 0 0 78px 30px rgb(0 0 0 / 0.65),
        inset 0 6px 20px rgb(255 220 180 / 0.06);
}

.dark .story-flipbook .cover-frame-ornate-baroque .cover-hard-frame::after {
    opacity: 0.85;
}

/* ─── Handmade cotton deckle (cream fog, fibrous scatter, uneven inset) ─── */
.story-flipbook .cover-frame-deckle-paper .cover-hard-frame {
    box-shadow:
        inset 0 0 0 1px rgb(120 90 60 / 0.2),
        inset 0 0 0 4px rgb(255 248 235 / 0.12),
        inset 0 0 0 7px rgb(90 70 50 / 0.12),
        inset 0 0 88px 36px rgb(139 90 60 / 0.22),
        inset 0 18px 40px rgb(255 252 245 / 0.14),
        inset 0 0 1px rgb(255 255 255 / 0.2);
}

.story-flipbook .cover-frame-deckle-paper .cover-hard-frame::before {
    inset: 10% 9% 12.5% 11%;
    border-radius: 10px 8px 12px 9px;
    box-shadow:
        inset 0 0 0 1px rgb(101 80 60 / 0.35),
        inset 0 12px 38px rgb(80 55 40 / 0.18),
        inset 0 2px 0 rgb(255 252 245 / 0.35);
}

.story-flipbook .cover-frame-deckle-paper .cover-hard-frame::after {
    opacity: 0.65;
    mix-blend-mode: multiply;
    background-image:
        radial-gradient(ellipse 100% 80% at 50% 50%, rgb(210 180 140 / 0.08), transparent 55%),
        repeating-conic-gradient(
            from 0deg at 50% 50%,
            transparent 0deg,
            transparent 2.2deg,
            rgb(90 60 40 / 0.03) 2.2deg,
            rgb(90 60 40 / 0.03) 2.8deg
        ),
        repeating-linear-gradient(
            95deg,
            transparent,
            transparent 2px,
            rgb(120 95 70 / 0.04) 2px,
            rgb(120 95 70 / 0.04) 3px
        );
}

.dark .story-flipbook .cover-frame-deckle-paper .cover-hard-frame {
    box-shadow:
        inset 0 0 0 1px rgb(255 250 240 / 0.08),
        inset 0 0 0 4px rgb(0 0 0 / 0.35),
        inset 0 0 0 7px rgb(90 75 60 / 0.2),
        inset 0 0 96px 40px rgb(0 0 0 / 0.45),
        inset 0 14px 36px rgb(255 245 230 / 0.06);
}

.dark .story-flipbook .cover-frame-deckle-paper .cover-hard-frame::before {
    border-radius: 10px 8px 12px 9px;
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.08),
        inset 0 12px 40px rgb(0 0 0 / 0.45),
        inset 0 2px 0 rgb(255 245 235 / 0.08);
}

.dark .story-flipbook .cover-frame-deckle-paper .cover-hard-frame::after {
    opacity: 0.45;
    mix-blend-mode: soft-light;
}

.story-flipbook .cover-frame-minimal-gilt .cover-hard-spine {
    width: min(3.4%, 14px);
    background: repeating-linear-gradient(
        180deg,
        rgb(0 0 0 / 0.1) 0px,
        rgb(0 0 0 / 0.1) 2px,
        rgb(255 255 255 / 0.18) 2px,
        rgb(255 255 255 / 0.18) 5px
    );
    box-shadow: inset -2px 0 5px rgb(0 0 0 / 0.12);
}

.dark .story-flipbook .cover-frame-minimal-gilt .cover-hard-spine {
    background: repeating-linear-gradient(
        180deg,
        rgb(0 0 0 / 0.35) 0px,
        rgb(0 0 0 / 0.35) 2px,
        rgb(255 255 255 / 0.07) 2px,
        rgb(255 255 255 / 0.07) 5px
    );
}

.story-flipbook .cover-frame-modern-bevel .cover-hard-spine {
    width: min(6%, 24px);
    background: repeating-linear-gradient(
        180deg,
        rgb(51 65 85 / 0.88) 0px,
        rgb(51 65 85 / 0.88) 3px,
        rgb(148 163 184 / 0.35) 3px,
        rgb(148 163 184 / 0.35) 6px
    );
    box-shadow: inset -4px 0 8px rgb(0 0 0 / 0.55);
}

.dark .story-flipbook .cover-frame-modern-bevel .cover-hard-spine {
    background: repeating-linear-gradient(
        180deg,
        rgb(30 41 59 / 0.95) 0px,
        rgb(30 41 59 / 0.95) 3px,
        rgb(100 116 139 / 0.25) 3px,
        rgb(100 116 139 / 0.25) 7px
    );
}

.story-flipbook .cover-frame-ornate-baroque .cover-hard-spine {
    width: min(6.8%, 27px);
    background: repeating-linear-gradient(
        180deg,
        rgb(50 28 12 / 0.65) 0px,
        rgb(50 28 12 / 0.65) 2px,
        rgb(212 175 55 / 0.28) 2px,
        rgb(212 175 55 / 0.28) 5px,
        rgb(35 18 8 / 0.7) 5px,
        rgb(35 18 8 / 0.7) 8px
    );
    box-shadow:
        inset -6px 0 12px rgb(0 0 0 / 0.5),
        0 0 14px rgb(196 154 58 / 0.12);
}

.dark .story-flipbook .cover-frame-ornate-baroque .cover-hard-spine {
    box-shadow:
        inset -6px 0 14px rgb(0 0 0 / 0.6),
        0 0 16px rgb(212 175 55 / 0.08);
}

.story-flipbook .cover-frame-deckle-paper .cover-hard-spine {
    width: min(5.2%, 21px);
    background: repeating-linear-gradient(
        180deg,
        rgb(139 110 85 / 0.4) 0px,
        rgb(139 110 85 / 0.4) 4px,
        rgb(255 248 235 / 0.14) 4px,
        rgb(255 248 235 / 0.14) 10px
    );
    box-shadow: inset -3px 0 9px rgb(90 70 50 / 0.3);
}

/* ─── Plain ─── */
.story-flipbook .cover-frame-none .cover-hard-frame {
    box-shadow: none;
}

.story-flipbook .cover-frame-none .cover-hard-frame::before,
.story-flipbook .cover-frame-none .cover-hard-frame::after {
    display: none;
}

.story-flipbook .cover-frame-none .cover-hard-spine {
    width: min(3.8%, 15px);
    opacity: 0.28;
    background: repeating-linear-gradient(
        180deg,
        rgb(0 0 0 / 0.12) 0px,
        rgb(0 0 0 / 0.12) 4px,
        rgb(255 255 255 / 0.05) 4px,
        rgb(255 255 255 / 0.05) 8px
    );
    box-shadow: inset -2px 0 4px rgb(0 0 0 / 0.18);
}

.story-flipbook .cover-hard-spine {
    position: absolute;
    top: 0;
    bottom: 0;
    z-index: 2;
    width: min(5%, 20px);
    pointer-events: none;
    background: repeating-linear-gradient(
        180deg,
        rgb(0 0 0 / 0.32) 0px,
        rgb(0 0 0 / 0.32) 4px,
        rgb(255 255 255 / 0.06) 4px,
        rgb(255 255 255 / 0.06) 8px
    );
    box-shadow: inset -4px 0 8px rgb(0 0 0 / 0.38);
}

.story-flipbook .cover-hard-spine--front {
    left: 0;
    border-radius: 3px 0 0 3px;
}

.story-flipbook .cover-hard-spine--back {
    right: 0;
    transform: scaleX(-1);
    border-radius: 3px 0 0 3px;
}

.story-flipbook .cover-end-title {
    text-shadow:
        0 1px 2px rgb(0 0 0 / 0.45),
        0 4px 18px rgb(0 0 0 / 0.35);
}

details summary::-webkit-details-marker {
    display: none;
}
</style>
