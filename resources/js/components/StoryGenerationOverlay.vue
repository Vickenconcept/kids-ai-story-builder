<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

type QueueSummary = {
    total: number;
    pending: number;
    running: number;
    succeeded: number;
    failed: number;
    last_error: string | null;
};

const props = defineProps<{
    visible: boolean;
    successTransition: boolean;
    haltedByCredits: boolean;
    status: string;
    pageCount: number;
    pagesCompleted: number;
    etaSeconds: number | null;
    queue: QueueSummary;
}>();

const emit = defineEmits<{
    close: [];
}>();

const processingLines = [
    'Warming up our story engines...',
    'Mapping scenes and characters...',
    'Painting illustrations and page details...',
    'Composing narration and quiz flow...',
    'Polishing your book for reading...',
];

const lineIndex = ref(0);
let lineTimer: ReturnType<typeof setInterval> | null = null;

const progressPercent = computed(() => {
    if (props.pageCount <= 0) {
        return 0;
    }
    return Math.max(0, Math.min(100, Math.round((props.pagesCompleted / props.pageCount) * 100)));
});

const showOverlay = computed(() => props.visible || props.haltedByCredits || props.successTransition);

const formattedEta = computed(() => {
    if (!props.etaSeconds || props.etaSeconds <= 0) {
        return null;
    }
    if (props.etaSeconds < 60) {
        return `~${props.etaSeconds}s remaining`;
    }
    const mins = Math.floor(props.etaSeconds / 60);
    const secs = props.etaSeconds % 60;
    return `~${mins}m ${secs}s remaining`;
});

const headline = computed(() =>
    props.haltedByCredits
        ? 'Generation paused: credits exhausted'
        : props.successTransition
          ? 'Story is ready'
          : 'Your story is generating',
);

const detailLine = computed(() =>
    props.haltedByCredits
        ? 'Add more credits, then create a new story to continue.'
        : props.successTransition
          ? 'Everything is complete. Opening your flipbook now...'
          : processingLines[lineIndex.value],
);

onMounted(() => {
    lineTimer = window.setInterval(() => {
        lineIndex.value = (lineIndex.value + 1) % processingLines.length;
    }, 2200);
});

onBeforeUnmount(() => {
    if (lineTimer) {
        clearInterval(lineTimer);
    }
});
</script>

<template>
    <div
        v-if="showOverlay"
        class="fixed inset-0 z-70 flex items-center justify-center p-4 backdrop-blur-sm transition-opacity duration-500"
        :class="successTransition ? 'bg-slate-950/55 opacity-0' : 'bg-slate-950/70 opacity-100'"
    >
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/15 bg-slate-900 text-slate-100 shadow-2xl">
            <div class="pointer-events-none relative h-24 overflow-hidden bg-linear-to-r from-cyan-500/20 via-teal-400/15 to-emerald-500/20">
                <div class="ai-wave ai-wave-a" />
                <div class="ai-wave ai-wave-b" />
            </div>

            <div class="space-y-5 p-6 sm:p-7">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ headline }}</h2>
                    <p class="mt-2 text-sm text-slate-300">
                        {{ detailLine }}
                    </p>
                </div>

                <div class="space-y-2 rounded-lg border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between text-xs text-slate-300">
                        <span>Story pages progress</span>
                        <span>{{ pagesCompleted }} / {{ pageCount }} ({{ progressPercent }}%)</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-700/70">
                        <div
                            class="h-full rounded-full bg-linear-to-r from-cyan-400 via-teal-400 to-emerald-400 transition-all duration-500"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <p v-if="formattedEta" class="text-[11px] text-slate-300">Estimated time: {{ formattedEta }}</p>
                </div>

                <div class="grid gap-2 text-xs text-slate-300 sm:grid-cols-2">
                    <p class="rounded-md border border-white/10 bg-white/5 px-3 py-2">Queue pending: {{ queue.pending }}</p>
                    <p class="rounded-md border border-white/10 bg-white/5 px-3 py-2">Queue running: {{ queue.running }}</p>
                    <p class="rounded-md border border-white/10 bg-white/5 px-3 py-2">Queue done: {{ queue.succeeded }}</p>
                    <p class="rounded-md border border-white/10 bg-white/5 px-3 py-2">Queue failed: {{ queue.failed }}</p>
                </div>

                <p v-if="haltedByCredits && queue.last_error" class="rounded-lg border border-rose-300/35 bg-rose-500/10 px-3 py-2 text-xs text-rose-100">
                    {{ queue.last_error }}
                </p>

                <div v-if="haltedByCredits" class="flex justify-end">
                    <Button type="button" variant="secondary" @click="emit('close')">Continue</Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ai-wave {
    position: absolute;
    left: -20%;
    width: 140%;
    height: 90px;
    border-radius: 100%;
    filter: blur(12px);
    animation: drift 4.2s ease-in-out infinite;
}

.ai-wave-a {
    top: 4px;
    background: radial-gradient(circle at 30% 50%, rgb(45 212 191 / 0.45), transparent 62%);
}

.ai-wave-b {
    top: 26px;
    animation-delay: 0.7s;
    animation-duration: 5s;
    background: radial-gradient(circle at 65% 50%, rgb(34 211 238 / 0.35), transparent 64%);
}

@keyframes drift {
    0% {
        transform: translateX(0) scale(1);
    }

    50% {
        transform: translateX(4%) scale(1.03);
    }

    100% {
        transform: translateX(0) scale(1);
    }
}
</style>
