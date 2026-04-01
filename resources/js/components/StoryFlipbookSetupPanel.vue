<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { Settings2 } from 'lucide-vue-next';
import { Label } from '@/components/ui/label';

export type SpreadAudioMode = 'first' | 'sequence';
export type AutoAdvanceMode = 'off' | 'timer' | 'afterAudio';

export type FlipbookSetupSettings = {
    audioOnFlip: boolean;
    spreadAudio: SpreadAudioMode;
    autoAdvance: AutoAdvanceMode;
    timerDelaySec: number;
    flipDuration: number;
    display: 'single' | 'double';
    gradients: boolean;
    acceleration: boolean;
    elevation: number;
    corners: string;
    pagesInDOM: number;
    bookZoomPercent: number;
};

const props = defineProps<{
    settings: FlipbookSetupSettings;
    playAudioOnFlip: boolean;
    includeQuiz: boolean;
    hasQuizPages: boolean;
    storyUuid?: string;
    setupMode: boolean;
    gameplayEnabledLocal: boolean;
    gameplayToggleBusy: boolean;
}>();

const emit = defineEmits<{
    'set-gameplay-enabled': [checked: boolean];
}>();
</script>

<template>
    <aside class="border-border bg-card/90 relative z-30 rounded-xl border p-3 text-sm shadow-sm xl:max-h-[calc(100vh-9rem)] xl:overflow-y-auto xl:pr-2">
        <div class="mb-4 flex items-center gap-2">
            <Settings2 class="size-4" />
            <p class="font-medium">Book &amp; narration settings</p>
        </div>

        <div class="space-y-3">
            <label class="hover:bg-muted/40 flex cursor-pointer items-start justify-between gap-3 rounded-lg border border-border/70 p-2.5 transition-colors">
                <span>
                    <span class="font-medium">Play narration when pages change</span>
                    <span class="text-muted-foreground block text-xs">
                        Narration follows the visible page spread.
                    </span>
                </span>
                <span class="relative mt-0.5 inline-flex">
                    <input
                        v-model="props.settings.audioOnFlip"
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
                class="hover:bg-muted/40 flex cursor-pointer items-start justify-between gap-3 rounded-lg border border-border/70 p-2.5 transition-colors"
            >
                <span>
                    <span class="font-medium">Enable quiz gameplay pages</span>
                    <span class="text-muted-foreground block text-xs">
                        Adds a quiz page after each story page that includes questions.
                    </span>
                </span>
                <span class="relative mt-0.5 inline-flex">
                    <input
                        :checked="gameplayEnabledLocal"
                        :disabled="gameplayToggleBusy"
                        type="checkbox"
                        class="peer sr-only"
                        @change="emit('set-gameplay-enabled', ($event.target as HTMLInputElement).checked)"
                    />
                    <span class="bg-muted peer-checked:bg-primary/80 peer-disabled:opacity-60 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                        <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                    </span>
                </span>
            </label>

            <div v-if="props.settings.audioOnFlip" class="space-y-1.5 rounded-lg border border-border/60 p-2.5">
                <div class="flex items-center gap-2">
                    <Label class="text-xs">Two-page spread narration</Label>
                    <span class="text-muted-foreground rounded border border-border px-1.5 py-0.5 text-[10px]" title="Visible when narration is enabled">
                        depends on narration
                    </span>
                </div>
                <select
                    v-model="props.settings.spreadAudio"
                    class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                >
                    <option value="first">Left page only</option>
                    <option value="sequence">Both pages in order</option>
                </select>
            </div>

            <div class="space-y-1.5 rounded-lg border border-border/60 p-2.5">
                <div class="flex items-center gap-2">
                    <Label class="text-xs">Auto-advance</Label>
                    <span class="text-muted-foreground rounded border border-border px-1.5 py-0.5 text-[10px]" title="Timer delay appears only when Timer mode is selected. After narration appears only when narration is enabled.">
                        has dependent options
                    </span>
                </div>
                <select
                    v-model="props.settings.autoAdvance"
                    class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                >
                    <option value="off">Off (manual flip only)</option>
                    <option value="timer">After delay</option>
                    <option v-if="props.settings.audioOnFlip" value="afterAudio">After narration finishes</option>
                </select>
            </div>

            <div v-if="props.settings.autoAdvance === 'timer'" class="space-y-1.5 rounded-lg border border-border/60 p-2.5">
                <div class="flex items-center gap-2">
                    <Label class="text-xs">Timer delay (seconds)</Label>
                    <span class="text-muted-foreground rounded border border-border px-1.5 py-0.5 text-[10px]" title="Visible only in Timer auto-advance mode">
                        timer mode only
                    </span>
                </div>
                <input
                    v-model.number="props.settings.timerDelaySec"
                    type="range"
                    min="2"
                    max="20"
                    step="1"
                    class="w-full"
                />
                <span class="text-muted-foreground text-xs">{{ props.settings.timerDelaySec }}s</span>
            </div>
        </div>

        <div class="border-border mt-4 border-t pt-3.5">
            <p class="mb-3 text-xs font-medium tracking-wide uppercase">Book behavior</p>
            <div class="space-y-2.5">
                <div class="space-y-1.5 rounded-lg border border-border/60 p-2.5">
                    <Label class="text-xs">Display mode</Label>
                    <select
                        v-model="props.settings.display"
                        class="border-input bg-background w-full rounded-md border px-2 py-2 text-sm"
                    >
                        <option value="double">Double page (spread)</option>
                        <option value="single">Single page</option>
                    </select>
                </div>
                <div class="space-y-1.5 rounded-lg border border-border/60 p-2.5">
                    <Label class="text-xs">Flip duration (ms)</Label>
                    <input
                        v-model.number="props.settings.flipDuration"
                        type="range"
                        min="300"
                        max="1200"
                        step="50"
                        class="w-full"
                    />
                    <span class="text-muted-foreground text-xs">{{ props.settings.flipDuration }} ms</span>
                </div>
                <div class="space-y-1.5 rounded-lg border border-border/60 p-2.5">
                    <Label class="text-xs">Page lift (elevation)</Label>
                    <input
                        v-model.number="props.settings.elevation"
                        type="range"
                        min="0"
                        max="100"
                        step="2"
                        class="w-full"
                    />
                    <span class="text-muted-foreground text-xs">{{ props.settings.elevation }} px</span>
                </div>
                <div class="space-y-1.5 rounded-lg border border-border/60 p-2.5">
                    <Label class="text-xs">Book zoom (%)</Label>
                    <input
                        v-model.number="props.settings.bookZoomPercent"
                        type="range"
                        min="75"
                        max="125"
                        step="1"
                        class="w-full"
                    />
                    <span class="text-muted-foreground text-xs">{{ props.settings.bookZoomPercent }}%</span>
                </div>
                <label class="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border/60 p-2.5 transition-colors">
                    <span class="text-sm">Gradients (page curl shading)</span>
                    <span class="relative inline-flex">
                        <input v-model="props.settings.gradients" type="checkbox" class="peer sr-only" />
                        <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                            <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                        </span>
                    </span>
                </label>
                <label class="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border/60 p-2.5 transition-colors">
                    <span class="text-sm">Hardware acceleration</span>
                    <span class="relative inline-flex">
                        <input v-model="props.settings.acceleration" type="checkbox" class="peer sr-only" />
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

        <slot />
    </aside>
</template>
