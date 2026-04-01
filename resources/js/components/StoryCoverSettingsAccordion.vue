<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { COVER_FRAME_OPTIONS, type CoverFrameId, normalizeCoverFrame } from '@/lib/coverFrames';

type CoverConfigJson = {
    kind: string;
    color?: string;
    angle?: number;
    from?: string;
    to?: string;
    path?: string;
    url?: string;
    prompt?: string;
    frame?: string;
} | null;

const props = defineProps<{
    storyUuid: string;
    title: string;
    topic: string;
    coverFront: CoverConfigJson;
    coverBack: CoverConfigJson;
}>();

const coverSurface = ref<'front' | 'back'>('front');
const solidColor = ref('#4f46e5');
const gradFrom = ref('#6366f1');
const gradTo = ref('#ec4899');
const gradAngle = ref(135);
const coverAiBusy = ref(false);

function patchCoverConfig(config: Record<string, string | number | boolean | null | undefined>): void {
    const payload = coverSurface.value === 'front' ? { cover_front: config } : { cover_back: config };
    router.patch(`/stories/${props.storyUuid}`, payload, { preserveScroll: true });
}

const activeSurfaceCover = computed(() =>
    coverSurface.value === 'front' ? props.coverFront : props.coverBack,
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
    router.post(`/stories/${props.storyUuid}/cover-upload`, fd, { preserveScroll: true });
}

function generateAiCover(): void {
    coverAiBusy.value = true;
    router.post(
        `/stories/${props.storyUuid}/cover-ai`,
        { surface: coverSurface.value },
        {
            preserveScroll: true,
            onFinish: () => {
                coverAiBusy.value = false;
            },
        },
    );
}
</script>

<template>
    <details class="border-border bg-background/60 group rounded-lg border">
        <summary class="hover:bg-muted/50 flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-sm font-medium">
            <span>Book covers (front &amp; back)</span>
            <span class="text-muted-foreground text-xs transition-transform group-open:rotate-180">▼</span>
        </summary>
        <div class="border-border space-y-4 border-t p-3">
            <p class="text-muted-foreground text-xs">
                Solid color, linear gradient, image upload (JPEG, PNG, WebP, or animated GIF),
                or AI-generated art. Public reader visitors see the same covers.
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
                    Border and embossing on the hard cover. Front and back can use different templates.
                </p>
                <div class="grid gap-2">
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
            <div class="space-y-3">
                <div class="space-y-2">
                    <Label class="text-xs">Solid color</Label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="solidColor" type="color" class="h-9 w-14 cursor-pointer rounded border" />
                        <Button type="button" size="sm" variant="secondary" @click="applySolidCover">
                            Apply
                        </Button>
                    </div>
                </div>
                <div class="space-y-2">
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
                        {{ coverAiBusy ? 'Generating...' : 'Generate with AI' }}
                    </Button>
                </div>
            </div>
        </div>
    </details>
</template>
