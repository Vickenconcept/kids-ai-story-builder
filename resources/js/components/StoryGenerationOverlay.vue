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
    buyCredits: [];
}>();

const processingLines = [
    'Creating your story pages...',
    'Writing your story...',
    'Preparing visuals and narration...',
    'Adding narration...',
    'Final touches before your story opens...',
    'Adding the finishing touches...',
    'Finishing up...',
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

const stageLabel = computed(() => {
    if (props.successTransition) {
        return 'Done';
    }

    if (progressPercent.value < 34) {
        return 'Writing story';
    }

    if (progressPercent.value < 84) {
        return 'Preparing media';
    }

    return 'Finishing up';
});

onMounted(() => {
    lineTimer = window.setInterval(() => {
        lineIndex.value = (lineIndex.value + 1) % processingLines.length;
    }, 4000);
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
        :class="successTransition ? 'bg-black/30 opacity-0' : 'bg-black/45 opacity-100'"
    >
        <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-background text-foreground shadow-2xl">
            <div class="pointer-events-none flex items-center justify-between border-b border-border bg-muted/45 px-5 py-3">
                <span class="text-xs font-semibold tracking-wide uppercase text-muted-foreground">{{ stageLabel }}</span>
                <div class="loading-dots" aria-hidden="true">
                    <span />
                    <span />
                    <span />
                </div>
            </div>

            <div class="space-y-5 p-5 sm:p-6">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ headline }}</h2>
                    <p class="text-muted-foreground mt-2 text-sm">
                        {{ detailLine }}
                    </p>
                </div>

                <div class="space-y-2 rounded-lg border border-border bg-muted/35 p-4">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Progress</span>
                        <span>{{ pagesCompleted }} / {{ pageCount }} ({{ progressPercent }}%)</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-500"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <p v-if="formattedEta" class="text-[11px] text-muted-foreground">Estimated time: {{ formattedEta }}</p>
                </div>

                <p v-if="haltedByCredits && queue.last_error" class="rounded-lg border border-rose-300/50 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ queue.last_error }}
                </p>

                <div v-if="haltedByCredits" class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="emit('buyCredits')">Buy Credits</Button>
                    <Button type="button" variant="secondary" @click="emit('close')">Continue</Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.loading-dots {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.loading-dots > span {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: hsl(var(--primary));
    animation: dotPulse 1.2s ease-in-out infinite;
}

.loading-dots > span:nth-child(2) {
    animation-delay: 0.16s;
}

.loading-dots > span:nth-child(3) {
    animation-delay: 0.32s;
}

@keyframes dotPulse {
    0% {
        opacity: 0.35;
        transform: scale(0.85);
    }

    100% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
