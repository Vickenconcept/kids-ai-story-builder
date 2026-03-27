<script setup lang="ts">
import { ChevronLeft, ChevronRight, Settings2 } from 'lucide-vue-next';
import type { JQueryStatic } from 'jquery';
import { router } from '@inertiajs/vue3';
import { createApp, h, computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import StoryQuizSheet, { type QuizRow } from '@/components/StoryQuizSheet.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

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
let jq: JQueryStatic | null = null;

const gameUnmounters: Array<() => void> = [];

function unmountGameApps(): void {
    while (gameUnmounters.length > 0) {
        gameUnmounters.pop()?.();
    }
}

function normalizeQuiz(raw: unknown): QuizRow[] {
    if (!Array.isArray(raw)) {
        return [];
    }
    return raw.map((item) => {
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

const FRONT_HARD_COUNT = 2;
const BACK_HARD_COUNT = 2;

type SpreadAudioMode = 'first' | 'sequence';
type AutoAdvanceMode = 'off' | 'timer' | 'afterAudio';

const settings = reactive({
    audioOnFlip: props.playAudioOnFlip,
    spreadAudio: 'sequence' as SpreadAudioMode,
    autoAdvance: 'off' as AutoAdvanceMode,
    timerDelaySec: 5,
    flipDuration: 650,
    display: 'double' as 'single' | 'double',
    gradients: true,
    acceleration: true,
    elevation: 48,
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

function serializeFlipSettingsForServer(): Record<string, unknown> {
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

let advanceTimer: ReturnType<typeof setTimeout> | null = null;

function clearAdvanceTimer(): void {
    if (advanceTimer !== null) {
        clearTimeout(advanceTimer);
        advanceTimer = null;
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

function storyIndicesInView(view: number[]): number[] {
    const { start, end } = contentPageRange();
    const mid = middleSlots();
    const idxs: number[] = [];
    for (const p of [...view].sort((a, b) => a - b)) {
        if (p < start || p > end) {
            continue;
        }
        const slot = mid[p - start];
        if (slot?.type === 'content') {
            idxs.push(slot.idx);
        }
    }
    return [...new Set(idxs)].sort((a, b) => a - b);
}

function isPlayableAudioUrl(url: string | null | undefined): boolean {
    return Boolean(url && !url.endsWith('.txt'));
}

function pauseNarration(): void {
    const el = pageAudioRef.value;
    if (!el) {
        return;
    }
    el.onended = null;
    el.pause();
}

function getTurnTotalPages(): number {
    if (!flipRoot.value || !jq) {
        return 0;
    }
    try {
        return jq(flipRoot.value).turn('pages') as number;
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
    if (!el || idx < 0 || idx >= props.pages.length) {
        onDone?.();
        return;
    }
    const url = props.pages[idx]?.audio_url;
    el.onended = null;
    if (!isPlayableAudioUrl(url)) {
        onDone?.();
        return;
    }
    el.src = url as string;
    el.onended = () => {
        el.onended = null;
        onDone?.();
    };
    void el.play().catch(() => {
        el.onended = null;
        onDone?.();
    });
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
        return jq(flipRoot.value).turn('view') as number[];
    } catch {
        return [];
    }
}

function onTurning(): void {
    clearAdvanceTimer();
    pauseNarration();
}

function onTurned(_e: unknown, _page: number, view: number[]): void {
    playSpreadNarration(view);
    scheduleTimerAdvance(view);
}

function bookDimensions() {
    const maxW = Math.min(920, typeof window !== 'undefined' ? window.innerWidth - 40 : 920);
    const w = Math.max(320, maxW);
    const h = Math.round(w * 0.62);
    return { w, h };
}

const scalerStyle = computed(() => ({
    transform: `scale(${settings.bookZoomPercent / 100})`,
    transformOrigin: 'center center',
}));

async function loadTurn(): Promise<void> {
    const jQuery = (await import('jquery')).default;
    window.jQuery = jQuery;
    window.$ = jQuery;
    jq = jQuery;
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

    const frontWrap = jq('<div class="hard cover-front" />').css(coverHardStyle(props.coverFront ?? null));
    frontWrap.append(
        jq('<div class="cover-hard-inner flex h-full flex-col items-center justify-center p-6 text-center" />').append(
            jq('<span class="cover-title text-lg font-semibold tracking-tight" />').text(props.title),
        ),
    );
    $root.append(frontWrap);
    $root.append(jq('<div class="hard hard-inside" />'));

    const slots = middleSlots();
    for (const slot of slots) {
        if (slot.type === 'content') {
            const p = slot.page;
            const inner = jq(
                '<div class="page-inner page-sheet flex h-full flex-col overflow-hidden bg-card" />',
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
                jq('<div class="shrink-0 border-t border-border/60 p-3 text-xs leading-snug sm:text-sm" />').append(
                    jq('<p class="text-foreground/90" />').text(p.text_content ?? ''),
                ),
            );
            $root.append(inner);
        } else {
            const mount = jq(
                '<div class="page-inner page-sheet story-game-sheet flex h-full flex-col overflow-hidden bg-card" />',
            );
            const holder = jq('<div class="game-sheet-mount min-h-0 flex-1 w-full" />').attr(
                'data-page-uuid',
                slot.page.uuid,
            );
            mount.append(holder);
            $root.append(mount);
        }
    }

    $root.append(jq('<div class="hard hard-inside-back" />'));
    const backWrap = jq('<div class="hard cover-back" />').css(coverHardStyle(props.coverBack ?? null));
    backWrap.append(
        jq(
            '<div class="cover-hard-inner flex h-full items-center justify-center p-6 text-center text-sm opacity-90" />',
        ).text('The end'),
    );
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
        when: {
            turning: onTurning,
            turned: onTurned,
        },
    });

    ready.value = true;

    const currentView = $root.turn('view') as number[];
    playSpreadNarration(currentView);
    scheduleTimerAdvance(currentView);

    await nextTick();
    mountGameApps();
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
    <div class="story-flipbook-container flex w-full flex-col items-center gap-4">
        <p v-if="setupMode" class="text-muted-foreground max-w-xl text-center text-xs">
            Drag the <strong>page corners</strong> to flip (mouse or touch). Keyboard:
            <kbd class="bg-muted rounded px-1">←</kbd> /
            <kbd class="bg-muted rounded px-1">→</kbd>. Powered by
            <a href="https://github.com/blasten/turn.js" class="underline" target="_blank" rel="noreferrer"
                >Turn.js</a
            >
            ·
            <a href="http://www.turnjs.com/" class="underline" target="_blank" rel="noreferrer">turnjs.com</a>
        </p>
        <p v-else class="text-muted-foreground max-w-md text-center text-xs">
            Drag the page corners or use
            <kbd class="bg-muted rounded px-1">←</kbd>
            /
            <kbd class="bg-muted rounded px-1">→</kbd>
            to turn the page.
        </p>

        <details
            v-if="setupMode"
            class="border-border bg-card/80 w-full max-w-2xl rounded-xl border text-sm shadow-sm backdrop-blur-sm"
        >
            <summary
                class="hover:bg-muted/40 flex cursor-pointer list-none items-center gap-2 rounded-xl px-4 py-3 font-medium select-none"
            >
                <Settings2 class="size-4" />
                Book &amp; narration settings
            </summary>
            <div class="border-border space-y-5 border-t px-4 py-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-2">
                        <input v-model="settings.audioOnFlip" type="checkbox" class="mt-1 size-4 rounded border" />
                        <span>
                            <span class="font-medium">Play audio when pages change</span>
                            <span class="text-muted-foreground block text-xs">Narration follows the open spread.</span>
                        </span>
                    </label>
                    <label
                        v-if="includeQuiz && hasQuizPages && storyUuid && setupMode"
                        class="flex cursor-pointer items-start gap-2 sm:col-span-2"
                    >
                        <input
                            :checked="gameplayEnabled"
                            type="checkbox"
                            class="mt-1 size-4 rounded border"
                            @change="setGameplayEnabled(($event.target as HTMLInputElement).checked)"
                        />
                        <span>
                            <span class="font-medium">Quiz pages in the flip book</span>
                            <span class="text-muted-foreground block text-xs">
                                After each story page that has questions, adds a full page to play the quiz (content →
                                game → content…).
                            </span>
                        </span>
                    </label>
                    <div class="space-y-1.5">
                        <Label class="text-xs">Two-page spread audio</Label>
                        <select
                            v-model="settings.spreadAudio"
                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                        >
                            <option value="first">Left page only</option>
                            <option value="sequence">Both pages in order (storytelling)</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs">Auto-advance</Label>
                        <select
                            v-model="settings.autoAdvance"
                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                        >
                            <option value="off">Off (manual flip only)</option>
                            <option value="timer">After delay (seconds below)</option>
                            <option value="afterAudio">After spread audio finishes</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
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

                <div class="border-border border-t pt-4">
                    <p class="mb-3 text-xs font-medium tracking-wide uppercase">Turn.js options</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Display</Label>
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
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="settings.gradients" type="checkbox" class="size-4 rounded border" />
                            <span class="text-sm">Gradients (page curl shading)</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="settings.acceleration" type="checkbox" class="size-4 rounded border" />
                            <span class="text-sm">Hardware acceleration</span>
                        </label>
                    </div>
                    <p class="text-muted-foreground mt-3 text-xs">
                        Display, duration, elevation, and gradients reload the book. Turn.js v3 (open-source) does not
                        include the commercial v4 zoom API; use book zoom above for a similar effect.
                    </p>
                </div>
            </div>
        </details>

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
                <div class="book-scaler" :style="scalerStyle">
                    <div
                        ref="flipRoot"
                        class="story-flipbook touch-none overflow-hidden rounded-lg border border-border"
                    />
                </div>
            </div>
            <div class="book-floor-shadow" aria-hidden="true" />
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

.story-flipbook .cover-title {
    text-shadow:
        0 1px 2px rgb(0 0 0 / 0.4),
        0 4px 20px rgb(0 0 0 / 0.35);
}

details summary::-webkit-details-marker {
    display: none;
}
</style>
