<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type CreditCosts = {
    text: number;
    image: number;
    audio: number;
    video: number;
};

const props = defineProps<{
    featureTier: string;
    storyCredits: number;
    creditCosts: CreditCosts;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stories', href: '/stories' },
    { title: 'Create', href: '/stories/create' },
];

const form = useForm({
    title: '',
    topic: '',
    lesson_type: 'moral',
    age_group: '6-8',
    page_count: 5,
    illustration_style: 'cartoon',
    include_quiz: false,
    include_narration: true,
    include_video: false,
});

function submit() {
    form.post('/stories');
}

function normalizePageCount(): void {
    if (typeof form.page_count !== 'number' || Number.isNaN(form.page_count)) {
        return;
    }

    if (form.page_count < 3) {
        form.page_count = 3;
    }

    if (form.page_count > 15) {
        form.page_count = 15;
    }
}

const isPro = props.featureTier === 'pro';

const pages = computed(() => {
    if (typeof form.page_count !== 'number' || Number.isNaN(form.page_count)) {
        return 3;
    }

    return Math.min(15, Math.max(3, form.page_count));
});

const costs = computed<CreditCosts>(() => ({
    text: Math.max(0, Number(props.creditCosts?.text ?? 0)),
    image: Math.max(0, Number(props.creditCosts?.image ?? 0)),
    audio: Math.max(0, Number(props.creditCosts?.audio ?? 0)),
    video: Math.max(0, Number(props.creditCosts?.video ?? 0)),
}));

const breakdown = computed(() => {
    const text = costs.value.text;
    const image = pages.value * costs.value.image;
    const audio = form.include_narration ? pages.value * costs.value.audio : 0;
    const video = isPro && form.include_video ? pages.value * costs.value.video : 0;
    const total = text + image + audio + video;

    return { text, image, audio, video, total };
});

const requiredWithNarrationOn = computed(() => {
    const video = isPro && form.include_video ? pages.value * costs.value.video : 0;

    return costs.value.text + (pages.value * costs.value.image) + (pages.value * costs.value.audio) + video;
});

const requiredWithVideoOn = computed(() => {
    const audio = form.include_narration ? pages.value * costs.value.audio : 0;

    return costs.value.text + (pages.value * costs.value.image) + audio + (pages.value * costs.value.video);
});

const canEnableNarration = computed(() => props.storyCredits >= requiredWithNarrationOn.value);
const canEnableVideo = computed(() => isPro && props.storyCredits >= requiredWithVideoOn.value);
const canSubmit = computed(() => props.storyCredits >= breakdown.value.total);
const remainingCredits = computed(() => props.storyCredits - breakdown.value.total);

watch(
    () => [pages.value, form.include_narration, form.include_video, isPro],
    () => {
        if (!isPro && form.include_video) {
            form.include_video = false;
        }

        if (form.include_video && !canEnableVideo.value) {
            form.include_video = false;
        }

        if (form.include_narration && !canEnableNarration.value) {
            form.include_narration = false;
        }
    },
    { immediate: true },
);

const lessonTypeOptions = [
    { value: 'moral', label: 'Moral lesson' },
    { value: 'science', label: 'Science concept' },
    { value: 'history', label: 'History theme' },
    { value: 'social', label: 'Social skills' },
    { value: 'language', label: 'Language learning' },
];

const ageGroupOptions = [
    { value: '4-5', label: 'Ages 4-5' },
    { value: '6-8', label: 'Ages 6-8' },
    { value: '9-12', label: 'Ages 9-12' },
    { value: '13+', label: 'Ages 13+' },
];

const illustrationStyleOptions = [
    { value: 'cartoon', label: 'Cartoon' },
    { value: 'watercolor', label: 'Watercolor' },
    { value: '3d', label: '3D render' },
    { value: 'storybook', label: 'Classic storybook' },
    { value: 'anime', label: 'Anime inspired' },
];
</script>

<template>
    <Head title="Create story" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-2xl flex-col gap-8 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">New story project</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Text runs first; images, audio, and optional video are queued independently per page.
                </p>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input id="title" v-model="form.title" required />
                    <p v-if="form.errors.title" class="text-destructive text-sm">
                        {{ form.errors.title }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="topic">Topic</Label>
                    <Input id="topic" v-model="form.topic" required />
                </div>

                <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                    <div class="grid gap-2">
                        <Label for="lesson_type">Lesson type</Label>
                        <select
                            id="lesson_type"
                            v-model="form.lesson_type"
                            class="border-input bg-background h-10 rounded-md border px-3 text-sm"
                        >
                            <option v-for="opt in lessonTypeOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="age_group">Age group</Label>
                        <select
                            id="age_group"
                            v-model="form.age_group"
                            class="border-input bg-background h-10 rounded-md border px-3 text-sm"
                        >
                            <option v-for="opt in ageGroupOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                    <div class="grid gap-2">
                        <Label for="page_count">Pages</Label>
                        <Input
                            id="page_count"
                            v-model.number="form.page_count"
                            type="number"
                            min="3"
                            max="15"
                            required
                            @input="normalizePageCount"
                        />
                        <p v-if="form.errors.page_count" class="text-destructive text-sm">
                            {{ form.errors.page_count }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="illustration_style">Illustration style</Label>
                        <select
                            id="illustration_style"
                            v-model="form.illustration_style"
                            class="border-input bg-background h-10 rounded-md border px-3 text-sm"
                        >
                            <option v-for="opt in illustrationStyleOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-lg border p-4">
                    <div class="rounded-lg border border-amber-300/60 bg-amber-50/60 p-3 text-sm">
                        <p class="font-semibold text-amber-900">Credits balance: {{ props.storyCredits }}</p>
                        <p class="mt-1 text-xs text-amber-900/80">
                            Estimated total for this setup: {{ breakdown.total }}
                            <span v-if="canSubmit">(remaining: {{ remainingCredits }})</span>
                            <span v-else>(short by {{ Math.abs(remainingCredits) }})</span>
                        </p>
                        <ul class="mt-2 space-y-1 text-xs text-amber-900/80">
                            <li>Text: {{ breakdown.text }} (fixed)</li>
                            <li>Images: {{ breakdown.image }} ({{ pages }} x {{ costs.image }})</li>
                            <li>Audio: {{ breakdown.audio }} <span v-if="form.include_narration">({{ pages }} x {{ costs.audio }})</span></li>
                            <li>Video: {{ breakdown.video }} <span v-if="isPro && form.include_video">({{ pages }} x {{ costs.video }})</span></li>
                        </ul>
                    </div>

                    <label class="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border/60 p-2.5 transition-colors">
                        <span>Include quizzes (per page)</span>
                        <span class="relative inline-flex">
                            <input v-model="form.include_quiz" type="checkbox" class="peer sr-only" />
                            <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                            </span>
                        </span>
                    </label>
                    <label class="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border/60 p-2.5 transition-colors">
                        <span>Include narration (TTS) - {{ pages * costs.audio }} credits</span>
                        <span class="relative inline-flex">
                            <input v-model="form.include_narration" :disabled="!form.include_narration && !canEnableNarration" type="checkbox" class="peer sr-only" />
                            <span class="bg-muted peer-checked:bg-primary/80 peer-disabled:opacity-55 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                            </span>
                        </span>
                    </label>
                    <label v-if="isPro" class="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border/60 p-2.5 transition-colors">
                        <span>Include page video (Pro) - {{ pages * costs.video }} credits</span>
                        <span class="relative inline-flex">
                            <input v-model="form.include_video" :disabled="!form.include_video && !canEnableVideo" type="checkbox" class="peer sr-only" />
                            <span class="bg-muted peer-checked:bg-primary/80 peer-disabled:opacity-55 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                            </span>
                        </span>
                    </label>
                    <p v-if="isPro && !canEnableVideo" class="text-destructive text-xs">
                        Not enough credits to enable video for {{ pages }} pages.
                    </p>
                    <p v-if="!form.include_narration && !canEnableNarration" class="text-destructive text-xs">
                        Not enough credits to enable narration for {{ pages }} pages.
                    </p>
                    <p v-else class="text-muted-foreground text-xs">
                        Video generation is available on the Pro tier.
                    </p>
                </div>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing || !canSubmit">Generate</Button>
                    <Button variant="outline" type="button" as-child>
                        <Link href="/stories">Cancel</Link>
                    </Button>
                </div>
                <p v-if="!canSubmit" class="text-destructive text-sm">
                    Insufficient credits for this setup. Required: {{ breakdown.total }}, available: {{ props.storyCredits }}.
                </p>
            </form>
        </div>
    </AppLayout>
</template>
