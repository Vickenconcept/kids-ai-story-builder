import '../../css/flipbook-realism.css';
<script setup lang="ts">
import { ChevronLeft, ChevronRight, Settings2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { createApp, h, computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import StoryQuizSheet, { type QuizRow } from '@/components/StoryQuizSheet.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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
    },
);

const flipRoot = ref<HTMLElement | null>(null);
const pageAudioRef = ref<HTMLAudioElement | null>(null);
const ready = ref(false);
/** In double-page mode, shifts the stage so a single visible cover (closed front or closed back) sits centered. */
const bookNudgePx = ref(0);
type JQueryStatic = typeof import('jquery');
let jq: JQueryStatic | null = null;

const gameUnmounters: Array<() => void> = [];

function unmountGameApps(): void {
    while (gameUnmounters.length > 0) {
        gameUnmounters.pop()?.();
    }
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
    router.patch(`/stories/${props.storyUuid}`, { flip_gameplay_enabled: checked }, { preserveScroll: true });
}

const FRONT_HARD_COUNT = 1;
const BACK_HARD_COUNT = 1;

type SpreadAudioMode = 'first' | 'sequence';
type AutoAdvanceMode = 'off' | 'timer' | 'afterAudio';

const settings = reactive({
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
});

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
    };
}

function applyFlipPayloadFromServer(o: Record<string, unknown>): void {
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
    };
}

function schedulePersistFlipSettingsToServer(): void {
    if (!props.setupMode || !props.storyUuid) {
        return;
    }
    if (persistFlipTimer !== null) {
        clearTimeout(persistFlipTimer);
    }
    persistFlipTimer = window.setTimeout(() => {
        persistFlipTimer = null;
        router.patch(
            `/stories/${props.storyUuid}`,
            { flip_settings: serializeFlipSettingsForServer() },
            { preserveScroll: true },
        );
    }, 450);
}

watch(
    () => ({ ...settings }),
    () => {
        saveSettings();
        schedulePersistFlipSettingsToServer();
    },
    { deep: true },
);

watch(
    () => props.flipSettings,
    (nextSettings) => {
        if (nextSettings && typeof nextSettings === 'object' && !Array.isArray(nextSettings)) {
            applyFlipPayloadFromServer(nextSettings as Record<string, unknown>);
        }
    },
    { deep: true },
);

watch(
    () => props.playAudioOnFlip,
    (canPlayNarration) => {
        if (!canPlayNarration) {
            settings.audioOnFlip = false;
            if (settings.autoAdvance === 'afterAudio') {
                settings.autoAdvance = 'off';
            }
        }
    },
);

watch(
    () => settings.audioOnFlip,
    (enabled) => {
        if (!enabled && settings.autoAdvance === 'afterAudio') {
            settings.autoAdvance = 'off';
        }
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
    const start = FRONT_HARD_COUNT + 1;
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
}

function onTurned(_e: unknown, pageOrView?: unknown, viewMaybe?: unknown): void {
    const view = resolveSpreadViewFromTurnEvent(pageOrView ?? [], viewMaybe ?? []);
    playSpreadNarration(view);
    scheduleTimerAdvance(view);
    syncBookHorizontalNudge();
    // Re-mount quiz/game sheets after each page turn
    mountGameApps();
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

const scalerStyle = computed(() => ({
    transform: `scale(${settings.bookZoomPercent / 100})`,
    transformOrigin: 'center center',
}));

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

    try {
        $root.turn('disable', true);
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
    for (const slot of slots) {
        if (slot.type === 'content') {
            const p = slot.page;
            const inner = jq(
                '<div class="page-inner page-sheet page-sheet-realistic flex h-full flex-col overflow-hidden bg-card" />',
            );
            if (p.image_url) {
                inner.append(
                    jq('<div class="relative min-h-0 flex-1 bg-muted/30" />').append(
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
                    ),
                );
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
    }

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

    $root.turn({
        width: w,
        height: h,
        autoCenter: true,
        gradients: settings.gradients,
        duration: settings.flipDuration,
        acceleration: useAcceleration,
        display: settings.display,
        elevation: settings.elevation,
        corners: settings.corners,
        pages: props.pages.length,
        pagesInDOM: settings.pagesInDOM,
        when: {
            turning: onTurning,
            turned: onTurned,
        },
    });

    ready.value = true;

    const currentView = $root.turn('view') as unknown as number[];
    playSpreadNarration(currentView);
    scheduleTimerAdvance(currentView);

    await nextTick();
    mountGameApps();
    syncBookHorizontalNudge();
}

function mountGameApps(): void {
    unmountGameApps();
    if (!flipRoot.value) {
        return;
    }
    const nodes = flipRoot.value.querySelectorAll('.game-sheet-mount');
    nodes.forEach((el) => {
        const uuid = el.getAttribute('data-page-uuid');
        const page = props.pages.find((p) => p.uuid === uuid);
        if (!uuid || !page) {
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
        gameUnmounters.push(() => {
            app.unmount();
        });
    });
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
    ],
    () => {
        if (!jq || !flipRoot.value) {
            return;
        }
        scheduleRebuildTurn();
    },
);

watch(
    () => [props.pages, props.gameplayEnabled, props.coverFront, props.coverBack, props.setupMode, props.flipSettings],
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
            jq(flipRoot.value).turn('disable', true);
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
            <aside class="border-border bg-card/90 rounded-xl border p-4 text-sm shadow-sm xl:max-h-[calc(100vh-9rem)] xl:overflow-y-auto xl:pr-2">
                <div class="mb-4 flex items-center gap-2">
                    <Settings2 class="size-4" />
                    <p class="font-medium">Book &amp; narration settings</p>
                </div>

                <div class="space-y-4">
                    <label class="flex cursor-pointer items-start justify-between gap-3">
                        <span>
                            <span class="font-medium">Play narration when pages change</span>
                            <span class="text-muted-foreground block text-xs">
                                Narration follows the visible page spread.
                            </span>
                        </span>
                        <span class="relative mt-0.5 inline-flex">
                            <input
                                v-model="settings.audioOnFlip"
                                :disabled="!playAudioOnFlip"
                                type="checkbox"
                                class="peer sr-only"
                            />
                            <span
                                class="bg-muted peer-checked:bg-primary/80 peer-disabled:bg-muted/60 inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            >
                                <span
                                    class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5"
                                />
                            </span>
                        </span>
                    </label>
                    <p v-if="!playAudioOnFlip" class="text-muted-foreground -mt-2 text-xs">
                        Narration is unavailable for this story, so narration controls are disabled.
                    </p>

                    <label
                        v-if="includeQuiz && hasQuizPages && storyUuid && setupMode"
                        class="flex cursor-pointer items-start justify-between gap-3"
                    >
                        <span>
                            <span class="font-medium">Enable quiz gameplay pages</span>
                            <span class="text-muted-foreground block text-xs">
                                Adds a quiz page after each story page that includes questions.
                            </span>
                        </span>
                        <span class="relative mt-0.5 inline-flex">
                            <input
                                :checked="gameplayEnabled"
                                type="checkbox"
                                class="peer sr-only"
                                @change="setGameplayEnabled(($event.target as HTMLInputElement).checked)"
                            />
                            <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                            </span>
                        </span>
                    </label>

                    <div v-if="settings.audioOnFlip" class="space-y-1.5">
                        <Label class="text-xs">Two-page spread narration</Label>
                        <select
                            v-model="settings.spreadAudio"
                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                        >
                            <option value="first">Left page only</option>
                            <option value="sequence">Both pages in order</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs">Auto-advance</Label>
                        <select
                            v-model="settings.autoAdvance"
                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                        >
                            <option value="off">Off (manual flip only)</option>
                            <option value="timer">After delay</option>
                            <option v-if="settings.audioOnFlip" value="afterAudio">After narration finishes</option>
                        </select>
                    </div>

                    <div v-if="settings.autoAdvance === 'timer'" class="space-y-1.5">
                        <Label class="text-xs">Timer delay (seconds)</Label>
                        <input
                            v-model.number="settings.timerDelaySec"
                            type="range"
                            min="2"
                            max="20"
                            step="1"
                            class="w-full"
                        />
                        <span class="text-muted-foreground text-xs">{{ settings.timerDelaySec }}s</span>
                    </div>
                </div>

                <div class="border-border mt-5 border-t pt-4">
                    <p class="mb-3 text-xs font-medium tracking-wide uppercase">Book behavior</p>
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Display mode</Label>
                            <select
                                v-model="settings.display"
                                class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                            >
                                <option value="double">Double page (spread)</option>
                                <option value="single">Single page</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Flip duration (ms)</Label>
                            <input
                                v-model.number="settings.flipDuration"
                                type="range"
                                min="300"
                                max="1200"
                                step="50"
                                class="w-full"
                            />
                            <span class="text-muted-foreground text-xs">{{ settings.flipDuration }} ms</span>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Page lift (elevation)</Label>
                            <input
                                v-model.number="settings.elevation"
                                type="range"
                                min="0"
                                max="100"
                                step="2"
                                class="w-full"
                            />
                            <span class="text-muted-foreground text-xs">{{ settings.elevation }} px</span>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Book zoom (%)</Label>
                            <input
                                v-model.number="settings.bookZoomPercent"
                                type="range"
                                min="75"
                                max="125"
                                step="1"
                                class="w-full"
                            />
                            <span class="text-muted-foreground text-xs">{{ settings.bookZoomPercent }}%</span>
                        </div>
                        <label class="flex cursor-pointer items-center justify-between gap-3">
                            <span class="text-sm">Gradients (page curl shading)</span>
                            <span class="relative inline-flex">
                                <input v-model="settings.gradients" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-center justify-between gap-3">
                            <span class="text-sm">Hardware acceleration</span>
                            <span class="relative inline-flex">
                                <input v-model="settings.acceleration" type="checkbox" class="peer sr-only" />
                                <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                                </span>
                            </span>
                        </label>
                    </div>
                    <p class="text-muted-foreground mt-3 text-xs">
                        Display, duration, elevation, and gradients rebuild the book preview so changes are accurately
                        rendered.
                    </p>
                </div>

                <slot name="setup-extra" />
            </aside>

            <section class="flex min-w-0 flex-col items-center gap-4">
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
            </section>
        </div>

        <div v-else class="flex w-full flex-col items-center gap-4">
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
}

.book-drop-shadow {
    position: relative;
    z-index: 1;
    filter: drop-shadow(0 28px 40px rgb(0 0 0 / 0.28)) drop-shadow(0 10px 16px rgb(0 0 0 / 0.12));
}

.dark .book-drop-shadow {
    filter: drop-shadow(0 32px 56px rgb(0 0 0 / 0.55)) drop-shadow(0 14px 22px rgb(0 0 0 / 0.35));
}

.book-horizontal-nudge {
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
