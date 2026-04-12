<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useCreditsModal } from '@/composables/useCreditsModal';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

/** Keep in sync with `StoreStoryProjectRequest` and DB (`title` varchar 255, `topic` text + max validation). */
const STORY_TITLE_MAX = 255;
const STORY_TOPIC_MAX = 10000;

function clampStr(value: string, max: number): string {
    if (value.length <= max) {
        return value;
    }

    return value.slice(0, max);
}

type CreditCosts = {
    text: number;
    image: number;
    audio: number;
    video: number;
};

type TemplateMediaProfile = 'text_image' | 'text_image_audio' | 'text_image_video';

type StoryTemplate = {
    id: string;
    name: string;
    description: string;
    niche: string;
    title: string;
    topic: string;
    lesson_type: string;
    age_group: string;
    page_count: number;
    illustration_style: string;
    include_quiz: boolean;
    include_narration: boolean;
    include_video: boolean;
    media_profile: TemplateMediaProfile;
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

const isPro = props.featureTier === 'pro' || props.featureTier === 'elite';
const isElite = props.featureTier === 'elite';

const creationMode = ref<'none' | 'manual' | 'template'>(isElite ? 'none' : 'manual');
const isTemplateDialogOpen = ref(false);
const templates = ref<StoryTemplate[]>([]);
const templatesLoading = ref(false);
const templatesError = ref<string | null>(null);
const templateSearch = ref('');
const templateNicheFilter = ref<'all' | string>('all');
const templateMediaFilter = ref<'all' | TemplateMediaProfile>('all');
const selectedTemplateId = ref<string | null>(null);

function submit() {
    form.post('/stories');
}

function setManualMode(): void {
    creationMode.value = 'manual';
}

function setTemplateMode(): void {
    creationMode.value = 'template';
    isTemplateDialogOpen.value = true;
}

function normalizePageCount(): void {
    if (typeof form.page_count !== 'number' || Number.isNaN(form.page_count)) {
        return;
    }

    if (form.page_count < 2) {
        form.page_count = 2;
    }

    if (form.page_count > 15) {
        form.page_count = 15;
    }
}

const selectedTemplate = computed(() =>
    templates.value.find((template) => template.id === selectedTemplateId.value) ?? null,
);

const showForm = computed(() => creationMode.value === 'manual' || selectedTemplate.value !== null);

const pages = computed(() => {
    if (typeof form.page_count !== 'number' || Number.isNaN(form.page_count)) {
        return 2;
    }

    return Math.min(15, Math.max(2, form.page_count));
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
const hasSelectedTemplateWhenNeeded = computed(() => creationMode.value !== 'template' || selectedTemplate.value !== null);
const canGenerate = computed(() => canSubmit.value && hasSelectedTemplateWhenNeeded.value);
const creditsModal = useCreditsModal();

const titleAtLimit = computed(() => form.title.length >= STORY_TITLE_MAX);
const topicAtLimit = computed(() => form.topic.length >= STORY_TOPIC_MAX);

watch(
    () => form.title,
    (v) => {
        const s = typeof v === 'string' ? v : String(v ?? '');
        const c = clampStr(s, STORY_TITLE_MAX);
        if (c !== s) {
            form.title = c;
        }
    },
    { flush: 'sync' },
);

watch(
    () => form.topic,
    (v) => {
        const s = typeof v === 'string' ? v : String(v ?? '');
        const c = clampStr(s, STORY_TOPIC_MAX);
        if (c !== s) {
            form.topic = c;
        }
    },
    { flush: 'sync' },
);

const nicheOptions = computed(() => {
    const set = new Set<string>();

    for (const template of templates.value) {
        set.add(template.niche);
    }

    return Array.from(set).sort((a, b) => a.localeCompare(b));
});

const filteredTemplates = computed(() => {
    const query = templateSearch.value.trim().toLowerCase();

    return templates.value
        .filter((template) => {
            if (templateNicheFilter.value !== 'all' && template.niche !== templateNicheFilter.value) {
                return false;
            }

            if (templateMediaFilter.value !== 'all' && template.media_profile !== templateMediaFilter.value) {
                return false;
            }

            if (query.length === 0) {
                return true;
            }

            return (
                template.name.toLowerCase().includes(query)
                || template.description.toLowerCase().includes(query)
                || template.niche.toLowerCase().includes(query)
            );
        })
        .sort((a, b) => estimateTemplateCredits(a) - estimateTemplateCredits(b));
});

function clampPages(value: number): number {
    return Math.min(15, Math.max(0, value));
}

function estimateTemplateCredits(template: StoryTemplate): number {
    const pageCount = Math.min(15, Math.max(2, template.page_count));
    const text = costs.value.text;
    const image = pageCount * costs.value.image;
    const audio = template.include_narration ? pageCount * costs.value.audio : 0;
    const video = template.include_video && isPro ? pageCount * costs.value.video : 0;

    return text + image + audio + video;
}

function templateDisableReason(template: StoryTemplate): string | null {
    if (template.include_video && !isPro) {
        return 'Pro required for video templates';
    }

    const required = estimateTemplateCredits(template);

    if (required > props.storyCredits) {
        return `Requires ${required} credits`;
    }

    return null;
}

function applyTemplate(template: StoryTemplate): void {
    const reason = templateDisableReason(template);

    if (reason) {
        return;
    }

    selectedTemplateId.value = template.id;
    form.title = clampStr(template.title, STORY_TITLE_MAX);
    form.topic = clampStr(template.topic, STORY_TOPIC_MAX);
    form.lesson_type = template.lesson_type;
    form.age_group = template.age_group;
    form.page_count = Math.min(15, Math.max(2, template.page_count));
    form.illustration_style = template.illustration_style;
    form.include_quiz = template.include_quiz;
    form.include_narration = template.include_narration;
    form.include_video = template.include_video && isPro;

    normalizePageCount();
    creationMode.value = 'template';
    isTemplateDialogOpen.value = false;
}

async function loadTemplates(): Promise<void> {
    templatesLoading.value = true;
    templatesError.value = null;

    try {
        const response = await fetch('/data/story-templates.json', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Failed to load story templates');
        }

        const payload = await response.json();

        if (!Array.isArray(payload)) {
            throw new Error('Invalid templates format');
        }

        templates.value = payload as StoryTemplate[];
    } catch {
        templatesError.value = 'Could not load templates right now. Please try again.';
        templates.value = [];
    } finally {
        templatesLoading.value = false;
    }
}

function mediaProfileLabel(profile: TemplateMediaProfile): string {
    if (profile === 'text_image_audio') {
        return 'Text + Image + Audio';
    }

    if (profile === 'text_image_video') {
        return 'Text + Image + Video';
    }

    return 'Text + Image';
}

onMounted(() => {
    loadTemplates();
});

const maxPagesWithNarration = computed(() => {
    const perPage = costs.value.image + costs.value.audio + (form.include_video && isPro ? costs.value.video : 0);
    const available = props.storyCredits - costs.value.text;

    if (perPage <= 0) {
        return 15;
    }

    return clampPages(Math.floor(available / perPage));
});

const maxPagesWithVideo = computed(() => {
    const perPage = costs.value.image + costs.value.video + (form.include_narration ? costs.value.audio : 0);
    const available = props.storyCredits - costs.value.text;

    if (perPage <= 0) {
        return 15;
    }

    return clampPages(Math.floor(available / perPage));
});

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

watch(
    () => creationMode.value,
    (mode) => {
        if (mode === 'manual') {
            selectedTemplateId.value = null;
        }
    },
);

const warnings = computed<string[]>(() => {
    const list: string[] = [];
    if (!form.include_narration && !canEnableNarration.value && pages.value > 0) {
        list.push(`Not enough credits for narration — you can afford up to ${maxPagesWithNarration.value} page(s) with audio.`);
    }
    if (isPro && !form.include_video && !canEnableVideo.value && pages.value > 0) {
        list.push(`Not enough credits for video — you can afford up to ${maxPagesWithVideo.value} page(s) with video.`);
    }
    if (!canSubmit.value) {
        list.push(`Insufficient credits: need ${breakdown.value.total}, you have ${props.storyCredits}.`);
    }
    if (creationMode.value === 'template' && !selectedTemplate.value) {
        list.push('Choose a template to continue.');
    }
    return list;
});

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
    { value: 'flat-vector', label: 'Flat vector' },
    { value: 'pencil-sketch', label: 'Pencil sketch' },
    { value: 'pixel-art', label: 'Pixel art' },
    { value: 'paper-collage', label: 'Paper collage' },
];
</script>

<template>
    <Head title="Create story" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-muted/30 flex flex-col gap-6 p-4 md:p-6 dark:bg-muted/10">

            <!-- Page header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">New Story Project</h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        AI writes the text first, then queues illustrations, narration and video per page.
                    </p>
                </div>
                <Button variant="outline" as-child size="sm">
                    <Link href="/stories">← Back to Stories</Link>
                </Button>
            </div>

            <!-- Elite mode chooser -->
            <div v-if="isElite" class="grid gap-4 sm:grid-cols-2 sm:max-w-xl mx-auto w-full">
                <button
                    type="button"
                    class="group relative rounded-2xl border-2 p-5 text-left transition-all"
                    :class="creationMode === 'manual'
                        ? 'border-violet-500 bg-violet-50/60 dark:bg-violet-950/20'
                        : 'border-border hover:border-violet-300 hover:bg-muted/30'"
                    @click="setManualMode"
                >
                    <div class="mb-2 flex size-9 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40">
                        <svg class="size-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </div>
                    <p class="font-semibold">Manual Prompt</p>
                    <p class="text-muted-foreground mt-1 text-sm">Full control — fill in every field yourself.</p>
                    <div v-if="creationMode === 'manual'" class="absolute right-3 top-3 size-5 rounded-full bg-violet-500 text-white grid place-items-center">
                        <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </button>

                <button
                    type="button"
                    class="group relative rounded-2xl border-2 p-5 text-left transition-all"
                    :class="creationMode === 'template'
                        ? 'border-violet-500 bg-violet-50/60 dark:bg-violet-950/20'
                        : 'border-border hover:border-violet-300 hover:bg-muted/30'"
                    @click="setTemplateMode"
                >
                    <div class="mb-2 flex size-9 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/40">
                        <svg class="size-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    </div>
                    <p class="font-semibold">From Template</p>
                    <p class="text-muted-foreground mt-1 text-sm">Pick a ready-made niche with credit estimates.</p>
                    <div v-if="creationMode === 'template'" class="absolute right-3 top-3 size-5 rounded-full bg-violet-500 text-white grid place-items-center">
                        <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </button>
            </div>

            <p v-if="isElite && creationMode === 'none'" class="text-muted-foreground text-center text-sm">
                Choose a creation mode above to continue.
            </p>

            <!-- Template selected banner -->
            <div
                v-if="isElite && creationMode === 'template'"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-violet-200 bg-violet-50/60 p-4 dark:border-violet-800/40 dark:bg-violet-950/20"
            >
                <div>
                    <p class="text-sm font-semibold text-violet-800 dark:text-violet-300">
                        {{ selectedTemplate ? `Template: ${selectedTemplate.name}` : 'No template selected yet' }}
                    </p>
                    <p v-if="selectedTemplate" class="text-xs text-violet-700/80 dark:text-violet-400 mt-0.5">
                        {{ mediaProfileLabel(selectedTemplate.media_profile) }} · {{ selectedTemplate.page_count }} pages
                    </p>
                    <p v-else class="text-xs text-destructive mt-0.5">Select a template to continue.</p>
                </div>
                <Button type="button" variant="outline" size="sm" @click="isTemplateDialogOpen = true">
                    {{ selectedTemplate ? 'Change Template' : 'Choose Template' }}
                </Button>
            </div>

            <!-- Main form + sidebar -->
            <form v-if="showForm" class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-8" @submit.prevent="submit">

                <!-- Left: form fields -->
                <div class="flex flex-1 flex-col gap-6 min-w-0">

                    <!-- Section: Story basics -->
                    <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-5 dark:border-sidebar-border">
                        <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            Story Basics
                        </h2>
                        <div class="flex flex-col gap-4">
                            <div class="grid gap-1.5">
                                <div class="flex items-end justify-between gap-2">
                                    <Label for="title" class="font-medium">Title</Label>
                                    <span
                                        class="text-xs tabular-nums"
                                        :class="titleAtLimit ? 'font-medium text-destructive' : 'text-muted-foreground'"
                                    >
                                        {{ form.title.length }} / {{ STORY_TITLE_MAX }}
                                    </span>
                                </div>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    placeholder="e.g. Luna and the Lost Stars"
                                    :maxlength="STORY_TITLE_MAX"
                                    required
                                    :class="
                                        cn(
                                            'h-11',
                                            titleAtLimit &&
                                                'border-destructive ring-1 ring-destructive/25 dark:ring-destructive/40',
                                        )
                                    "
                                />
                                <p v-if="form.errors.title" class="text-destructive text-xs">{{ form.errors.title }}</p>
                                <p v-else class="text-muted-foreground text-xs">Give your story a short, catchy title.</p>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-end justify-between gap-2">
                                    <Label for="topic" class="font-medium">Topic / Premise</Label>
                                    <span
                                        class="text-xs tabular-nums"
                                        :class="topicAtLimit ? 'font-medium text-destructive' : 'text-muted-foreground'"
                                    >
                                        {{ form.topic.length }} / {{ STORY_TOPIC_MAX }}
                                    </span>
                                </div>
                                <textarea
                                    id="topic"
                                    v-model="form.topic"
                                    placeholder="e.g. A young girl discovers a hidden world beneath the ocean and learns about bravery and friendship."
                                    rows="3"
                                    :maxlength="STORY_TOPIC_MAX"
                                    required
                                    :class="
                                        cn(
                                            'border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-lg border px-3 py-2 text-sm resize-none focus-visible:outline-none focus-visible:ring-1',
                                            topicAtLimit &&
                                                'border-destructive ring-1 ring-destructive/25 dark:ring-destructive/40',
                                        )
                                    "
                                />
                                <p class="text-muted-foreground text-xs">Describe what the story should be about.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Story settings -->
                    <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-5 dark:border-sidebar-border">
                        <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Story Settings
                        </h2>
                        <div class="flex flex-col gap-5">

                            <!-- Lesson + Age row -->
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <Label for="lesson_type" class="font-medium">Lesson Type</Label>
                                    <select
                                        id="lesson_type"
                                        v-model="form.lesson_type"
                                        class="border-input bg-background focus-visible:ring-ring h-11 w-full rounded-lg border px-3 text-sm focus-visible:outline-none focus-visible:ring-1"
                                    >
                                        <option v-for="opt in lessonTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label class="font-medium">Age Group</Label>
                                    <div class="flex gap-2 flex-wrap">
                                        <button
                                            v-for="opt in ageGroupOptions"
                                            :key="opt.value"
                                            type="button"
                                            class="rounded-lg border px-3 py-2 text-sm transition-all"
                                            :class="form.age_group === opt.value
                                                ? 'border-violet-500 bg-violet-50 text-violet-700 font-semibold dark:bg-violet-900/30 dark:text-violet-300'
                                                : 'border-border hover:border-violet-300 hover:bg-muted/30'"
                                            @click="form.age_group = opt.value"
                                        >
                                            {{ opt.label }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Page count stepper -->
                            <div class="grid gap-1.5">
                                <Label class="font-medium">Number of Pages</Label>
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-border text-lg font-bold transition-colors hover:bg-muted disabled:opacity-40"
                                        :disabled="form.page_count <= 2"
                                        @click="form.page_count = Math.max(2, form.page_count - 1)"
                                    >−</button>
                                    <div class="flex flex-col items-center gap-0.5 min-w-16">
                                        <span class="text-2xl font-bold">{{ form.page_count }}</span>
                                        <span class="text-muted-foreground text-xs">pages</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-border text-lg font-bold transition-colors hover:bg-muted disabled:opacity-40"
                                        :disabled="form.page_count >= 15"
                                        @click="form.page_count = Math.min(15, form.page_count + 1)"
                                    >+</button>
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-violet-500 transition-all"
                                            :style="{ width: `${((form.page_count - 2) / 13) * 100}%` }"
                                        />
                                    </div>
                                    <span class="text-muted-foreground text-xs shrink-0">max 15</span>
                                </div>
                                <p v-if="form.errors.page_count" class="text-destructive text-xs">{{ form.errors.page_count }}</p>
                            </div>

                            <!-- Illustration style -->
                            <div class="grid gap-1.5">
                                <Label class="font-medium">Illustration Style</Label>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    <button
                                        v-for="opt in illustrationStyleOptions"
                                        :key="opt.value"
                                        type="button"
                                        class="rounded-xl border-2 px-3 py-2.5 text-center text-xs font-medium transition-all"
                                        :class="form.illustration_style === opt.value
                                            ? 'border-violet-500 bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'
                                            : 'border-border hover:border-violet-300 hover:bg-muted/30'"
                                        @click="form.illustration_style = opt.value"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Features / toggles -->
                    <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-5 dark:border-sidebar-border">
                        <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                            Features
                        </h2>
                        <div class="flex flex-col gap-3">

                            <!-- Quizzes toggle -->
                            <label
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border-2 p-4 transition-all"
                                :class="form.include_quiz
                                    ? 'border-violet-400 bg-violet-50/60 dark:border-violet-600 dark:bg-violet-950/20'
                                    : 'border-border/50 bg-muted/20 opacity-70 hover:opacity-100 hover:border-border'"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors"
                                        :class="form.include_quiz ? 'bg-violet-100 dark:bg-violet-900/40' : 'bg-muted'"
                                    >
                                        <svg class="size-4" :class="form.include_quiz ? 'text-violet-600' : 'text-muted-foreground'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold" :class="form.include_quiz ? 'text-violet-800 dark:text-violet-200' : ''">Include Quizzes</p>
                                        <p class="text-xs mt-0.5" :class="form.include_quiz ? 'text-violet-700/70 dark:text-violet-400' : 'text-muted-foreground'">Add a quiz question at the end of each page</p>
                                    </div>
                                </div>
                                <span class="relative inline-flex shrink-0" @click.prevent="form.include_quiz = !form.include_quiz">
                                    <span class="inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                                        :class="form.include_quiz ? 'bg-violet-500' : 'bg-muted'">
                                        <span class="ml-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200"
                                            :class="form.include_quiz ? 'translate-x-5' : 'translate-x-0'" />
                                    </span>
                                </span>
                            </label>

                            <!-- Narration toggle -->
                            <label
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border-2 p-4 transition-all"
                                :class="form.include_narration
                                    ? 'border-blue-400 bg-blue-50/60 dark:border-blue-600 dark:bg-blue-950/20'
                                    : 'border-border/50 bg-muted/20 opacity-70 hover:opacity-100 hover:border-border'"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors"
                                        :class="form.include_narration ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-muted'"
                                    >
                                        <svg class="size-4" :class="form.include_narration ? 'text-blue-600' : 'text-muted-foreground'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M12 6v12m-3.536-9.536a5 5 0 000 7.072M8.464 8.464A7 7 0 003 12m18 0a7 7 0 00-5.536-6.857"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold flex flex-wrap items-center gap-1.5" :class="form.include_narration ? 'text-blue-800 dark:text-blue-200' : ''">
                                            Include Narration
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="form.include_narration ? 'bg-blue-200 text-blue-800 dark:bg-blue-800/40 dark:text-blue-300' : 'bg-muted text-muted-foreground'">
                                                {{ pages * costs.audio }} credits
                                            </span>
                                        </p>
                                        <p class="text-xs mt-0.5" :class="form.include_narration ? 'text-blue-700/70 dark:text-blue-400' : 'text-muted-foreground'">AI text-to-speech audio for every page</p>
                                    </div>
                                </div>
                                <span
                                    class="relative inline-flex shrink-0"
                                    :class="(!form.include_narration && !canEnableNarration) ? 'opacity-40 pointer-events-none' : 'cursor-pointer'"
                                    @click.prevent="(!form.include_narration && !canEnableNarration) ? null : (form.include_narration = !form.include_narration)"
                                >
                                    <span class="inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                                        :class="form.include_narration ? 'bg-blue-500' : 'bg-muted'">
                                        <span class="ml-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200"
                                            :class="form.include_narration ? 'translate-x-5' : 'translate-x-0'" />
                                    </span>
                                </span>
                            </label>

                            <!-- Video toggle (Pro) -->
                            <label
                                v-if="isPro"
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border-2 p-4 transition-all"
                                :class="form.include_video
                                    ? 'border-violet-400 bg-violet-50/60 dark:border-violet-600 dark:bg-violet-950/20'
                                    : 'border-border/50 bg-muted/20 opacity-70 hover:opacity-100 hover:border-border'"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors"
                                        :class="form.include_video ? 'bg-violet-100 dark:bg-violet-900/40' : 'bg-muted'"
                                    >
                                        <svg class="size-4" :class="form.include_video ? 'text-violet-600' : 'text-muted-foreground'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold flex flex-wrap items-center gap-1.5" :class="form.include_video ? 'text-violet-800 dark:text-violet-200' : ''">
                                            Include Page Video
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="form.include_video ? 'bg-violet-200 text-violet-800 dark:bg-violet-800/40 dark:text-violet-300' : 'bg-muted text-muted-foreground'">Pro</span>
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="form.include_video ? 'bg-violet-200 text-violet-800 dark:bg-violet-800/40 dark:text-violet-300' : 'bg-muted text-muted-foreground'">
                                                {{ pages * costs.video }} credits
                                            </span>
                                        </p>
                                        <p class="text-xs mt-0.5" :class="form.include_video ? 'text-violet-700/70 dark:text-violet-400' : 'text-muted-foreground'">AI-generated video for each illustrated page</p>
                                    </div>
                                </div>
                                <span
                                    class="relative inline-flex shrink-0"
                                    :class="(!form.include_video && !canEnableVideo) ? 'opacity-40 pointer-events-none' : 'cursor-pointer'"
                                    @click.prevent="(!form.include_video && !canEnableVideo) ? null : (form.include_video = !form.include_video)"
                                >
                                    <span class="inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                                        :class="form.include_video ? 'bg-violet-500' : 'bg-muted'">
                                        <span class="ml-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200"
                                            :class="form.include_video ? 'translate-x-5' : 'translate-x-0'" />
                                    </span>
                                </span>
                            </label>

                            <!-- Non-pro video nudge -->
                            <div
                                v-if="!isPro"
                                class="flex items-start gap-3 rounded-xl border border-violet-200/60 bg-violet-50/60 p-4 dark:border-violet-800/40 dark:bg-violet-950/20"
                            >
                                <svg class="mt-0.5 size-4 shrink-0 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-violet-800 dark:text-violet-300">Video generation requires Pro</p>
                                    <p class="text-xs text-violet-700/70 dark:text-violet-400 mt-0.5">Upgrade to unlock AI video for every story page.</p>
                                    <Link href="/plans" class="mt-2 inline-block text-xs font-semibold text-violet-600 hover:underline">Upgrade to Pro →</Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: sticky credit summary -->
                <div class="lg:w-72 lg:shrink-0">
                    <div class="sticky top-4 flex flex-col gap-4">

                        <!-- Credit breakdown card -->
                        <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm p-5 dark:border-sidebar-border">
                            <h3 class="mb-3 text-sm font-semibold">Credit Estimate</h3>

                            <!-- Warnings panel — shown at the top so it's impossible to miss -->
                            <transition name="warnings-fade">
                                <div
                                    v-if="warnings.length"
                                    class="mb-4 rounded-xl border border-amber-300/70 bg-amber-50 px-3 py-2.5 dark:border-amber-700/50 dark:bg-amber-950/30"
                                >
                                    <div class="mb-1.5 flex items-center gap-1.5">
                                        <svg class="size-4 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">Action needed</span>
                                    </div>
                                    <ul class="space-y-1">
                                        <li v-for="(w, i) in warnings" :key="i" class="flex items-start gap-1.5 text-xs text-amber-800 dark:text-amber-300">
                                            <span class="mt-0.5 shrink-0">•</span>
                                            <span>{{ w }}</span>
                                        </li>
                                    </ul>
                                    <button
                                        v-if="!canSubmit"
                                        type="button"
                                        class="mt-2.5 w-full rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-700"
                                        @click="creditsModal.open()"
                                    >
                                        Buy Credits →
                                    </button>
                                </div>
                            </transition>

                            <!-- Balance -->
                            <div class="mb-4 flex items-center justify-between rounded-xl bg-muted/50 px-3 py-2">
                                <span class="text-xs text-muted-foreground">Your balance</span>
                                <span class="font-bold text-amber-600 dark:text-amber-400">{{ props.storyCredits }} credits</span>
                            </div>

                            <!-- Breakdown rows -->
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Story text</span>
                                    <span class="font-medium">{{ breakdown.text }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Illustrations (×{{ pages }})</span>
                                    <span class="font-medium">{{ breakdown.image }}</span>
                                </div>
                                <div class="flex justify-between" :class="!form.include_narration ? 'opacity-40' : ''">
                                    <span class="text-muted-foreground">Narration (×{{ pages }})</span>
                                    <span class="font-medium">{{ breakdown.audio }}</span>
                                </div>
                                <div v-if="isPro" class="flex justify-between" :class="!form.include_video ? 'opacity-40' : ''">
                                    <span class="text-muted-foreground">Video (×{{ pages }})</span>
                                    <span class="font-medium">{{ breakdown.video }}</span>
                                </div>
                                <div class="mt-1 border-t pt-2 flex justify-between font-semibold">
                                    <span>Total cost</span>
                                    <span :class="canSubmit ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">
                                        {{ breakdown.total }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-xs" :class="canSubmit ? 'text-muted-foreground' : 'text-destructive font-semibold'">
                                    <span>{{ canSubmit ? 'Remaining after' : 'Short by' }}</span>
                                    <span>{{ Math.abs(remainingCredits) }}</span>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="canSubmit ? 'bg-emerald-500' : 'bg-destructive'"
                                    :style="{ width: `${Math.min(100, (breakdown.total / Math.max(1, props.storyCredits)) * 100)}%` }"
                                />
                            </div>
                        </div>

                        <!-- CTA buttons -->
                        <div class="flex flex-col gap-2">
                            <Button
                                type="submit"
                                class="h-11 w-full bg-violet-600 text-white hover:bg-violet-700"
                                :disabled="form.processing || !canGenerate"
                            >
                                <svg v-if="form.processing" class="mr-2 size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                                {{ form.processing ? 'Generating…' : '✨ Generate Story' }}
                            </Button>
                            <Button variant="outline" type="button" class="w-full" as-child>
                                <Link href="/stories">Cancel</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Template picker dialog -->
            <Dialog v-if="isElite" v-model:open="isTemplateDialogOpen">
                <DialogContent class="sm:max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Choose a Story Template</DialogTitle>
                        <DialogDescription>
                            Browse ready-made niches and media profiles. Templates are sorted by credit cost.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <Label for="template-search">Search</Label>
                            <Input id="template-search" v-model="templateSearch" placeholder="Name, niche, or description…" />
                        </div>
                        <div>
                            <Label for="template-niche">Niche</Label>
                            <select
                                id="template-niche"
                                v-model="templateNicheFilter"
                                class="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="all">All niches</option>
                                <option v-for="niche in nicheOptions" :key="niche" :value="niche">{{ niche }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-2">
                        <Label for="template-media">Output type</Label>
                        <select
                            id="template-media"
                            v-model="templateMediaFilter"
                            class="border-input bg-background mt-1 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="all">All output types</option>
                            <option value="text_image">Text + Image</option>
                            <option value="text_image_audio">Text + Image + Audio</option>
                            <option value="text_image_video">Text + Image + Video</option>
                        </select>
                    </div>

                    <p v-if="templatesLoading" class="text-muted-foreground text-sm">Loading templates…</p>
                    <p v-else-if="templatesError" class="text-destructive text-sm">{{ templatesError }}</p>
                    <p v-else-if="filteredTemplates.length === 0" class="text-muted-foreground text-sm">No templates match your filters.</p>

                    <div v-else class="max-h-96 overflow-y-auto pr-1">
                        <div class="grid gap-3 md:grid-cols-3">
                            <button
                                v-for="template in filteredTemplates"
                                :key="template.id"
                                type="button"
                                class="rounded-xl border-2 p-3 text-left transition-all"
                                :class="templateDisableReason(template)
                                    ? 'cursor-not-allowed border-border/40 opacity-50'
                                    : selectedTemplateId === template.id
                                        ? 'border-violet-500 bg-violet-50/60 dark:bg-violet-950/20'
                                        : 'border-border hover:border-violet-300 hover:bg-muted/30'"
                                :disabled="Boolean(templateDisableReason(template))"
                                @click="applyTemplate(template)"
                            >
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ template.niche }}</p>
                                <h3 class="mt-1 text-sm font-semibold">{{ template.name }}</h3>
                                <p class="text-muted-foreground mt-1 line-clamp-2 text-xs">{{ template.description }}</p>
                                <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                                    <span class="rounded-full bg-muted px-2 py-0.5">{{ mediaProfileLabel(template.media_profile) }}</span>
                                    <span class="rounded-full bg-muted px-2 py-0.5">{{ template.page_count }} pages</span>
                                    <span class="rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 dark:bg-amber-900/30 dark:text-amber-400">{{ estimateTemplateCredits(template) }} cr</span>
                                </div>
                                <p v-if="templateDisableReason(template)" class="text-destructive mt-2 text-xs">{{ templateDisableReason(template) }}</p>
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
.warnings-fade-enter-active,
.warnings-fade-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease, max-height 0.3s ease;
    overflow: hidden;
    max-height: 200px;
}
.warnings-fade-enter-from,
.warnings-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
    max-height: 0;
}
</style>
