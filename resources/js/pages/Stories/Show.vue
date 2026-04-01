<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import StoryFlipbook, { type CoverConfigJson } from '@/components/StoryFlipbook.vue';
import StoryCoverSettingsAccordion from '@/components/StoryCoverSettingsAccordion.vue';
import StoryGenerationOverlay from '@/components/StoryGenerationOverlay.vue';
import StorySetupTopBar from '@/components/StorySetupTopBar.vue';

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
        queue: {
            total: number;
            pending: number;
            running: number;
            succeeded: number;
            failed: number;
            last_error: string | null;
        };
    };
    pages: PageRow[];
    story_credits: number;
}>();

const viewMode = ref<'flip' | 'scroll'>('flip');
const creditsOverlayDismissed = ref(false);
const generationSuccessTransition = ref(false);
const flipbookSectionRef = ref<HTMLElement | null>(null);

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

const isGenerating = computed(() => props.project.status !== 'ready' && props.project.status !== 'failed');

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
            @update:view-mode="viewMode = $event"
        />

        <div class="mx-auto w-full max-w-440 space-y-4 px-4 py-3 sm:px-6">
            <div
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

            <section class="min-w-0 space-y-4">
                <div
                    v-if="viewMode === 'flip' && pages.length > 0"
                    ref="flipbookSectionRef"
                    tabindex="-1"
                    class="border-border bg-card/30 w-full rounded-xl border p-3 shadow-sm"
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
            </section>
        </div>
    </div>
</template>
