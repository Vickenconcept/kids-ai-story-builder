<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
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
import type { BreadcrumbItem } from '@/types';

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

const creationMode = ref<'none' | 'manual' | 'template'>('none');
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

const isPro = props.featureTier === 'pro';

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
    form.title = template.title;
    form.topic = template.topic;
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

            <div
                class="grid gap-4 sm:grid-cols-2"
                :class="creationMode === 'none' ? 'mx-auto w-full max-w-3xl' : ''"
            >
                <button
                    type="button"
                    class="rounded-xl border p-6 text-left transition-colors md:min-h-40"
                    :class="creationMode === 'manual' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/30'"
                    @click="setManualMode"
                >
                    <p class="text-base font-semibold">Enter Prompt Manually</p>
                    <p class="text-muted-foreground mt-2 text-sm">
                        Full control. Use the form below to customize every setting.
                    </p>
                </button>

                <button
                    type="button"
                    class="rounded-xl border p-6 text-left transition-colors md:min-h-40"
                    :class="creationMode === 'template' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/30'"
                    @click="setTemplateMode"
                >
                    <p class="text-base font-semibold">Create From Template</p>
                    <p class="text-muted-foreground mt-2 text-sm">
                        Pick from ready-made niches and media profiles with credit estimates.
                    </p>
                </button>
            </div>

            <p v-if="creationMode === 'none'" class="text-muted-foreground text-center text-sm">
                Choose how you want to start: manual prompt or template.
            </p>

            <div v-if="creationMode === 'template'" class="rounded-lg border p-4">
                <p class="text-sm font-semibold">Template mode selected</p>
                <p v-if="selectedTemplate" class="text-muted-foreground mt-1 text-xs">
                    Using template: <span class="font-medium text-foreground">{{ selectedTemplate.name }}</span>
                    ({{ mediaProfileLabel(selectedTemplate.media_profile) }})
                </p>
                <p v-else class="text-destructive mt-1 text-xs">
                    No template selected yet. Choose one to continue.
                </p>
                <div class="mt-3">
                    <Button type="button" variant="outline" @click="isTemplateDialogOpen = true">
                        Choose Template
                    </Button>
                </div>
            </div>

            <form v-if="showForm" class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input id="title" v-model="form.title" placeholder="e.g. Luna and the Lost Stars" required />
                    <p class="text-muted-foreground text-xs">Give your story a short, catchy title.</p>
                    <p v-if="form.errors.title" class="text-destructive text-sm">
                        {{ form.errors.title }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="topic">Topic</Label>
                    <Input id="topic" v-model="form.topic" placeholder="e.g. Friendship, planets, and teamwork" required />
                    <p class="text-muted-foreground text-xs">Describe what the story should be about.</p>
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
                            min="2"
                            max="15"
                            placeholder="2-15"
                            required
                            @input="normalizePageCount"
                        />
                        <p class="text-muted-foreground text-xs">Choose between 2 and 15 pages.</p>
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
                        Not enough credits to enable video for {{ pages }} pages. You can afford up to {{ maxPagesWithVideo }} page(s) with video.
                    </p>
                    <p v-if="!form.include_narration && !canEnableNarration" class="text-destructive text-xs">
                        Not enough credits to enable narration for {{ pages }} pages. You can afford up to {{ maxPagesWithNarration }} page(s) with narration.
                    </p>
                    <p v-else class="text-muted-foreground text-xs">
                        Video generation is available on the Pro tier.
                    </p>
                </div>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing || !canGenerate">Generate</Button>
                    <Button variant="outline" type="button" as-child>
                        <Link href="/stories">Cancel</Link>
                    </Button>
                </div>
                <p v-if="creationMode === 'template' && !selectedTemplate" class="text-destructive text-sm">
                    Choose a template first to continue in template mode.
                </p>
                <p v-if="!canSubmit" class="text-destructive text-sm">
                    Insufficient credits for this setup. Required: {{ breakdown.total }}, available: {{ props.storyCredits }}.
                </p>
            </form>

            <Dialog v-model:open="isTemplateDialogOpen">
                <DialogContent class="sm:max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Choose a Story Template</DialogTitle>
                        <DialogDescription>
                            Search and filter templates by niche and output type. Templates that cost more than your
                            current credits are disabled.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <Label for="template-search">Search templates</Label>
                            <Input
                                id="template-search"
                                v-model="templateSearch"
                                placeholder="Search by template name, niche, or description"
                            />
                        </div>
                        <div>
                            <Label for="template-niche">Niche</Label>
                            <select
                                id="template-niche"
                                v-model="templateNicheFilter"
                                class="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="all">All niches</option>
                                <option v-for="niche in nicheOptions" :key="niche" :value="niche">
                                    {{ niche }}
                                </option>
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

                    <p v-if="templatesLoading" class="text-muted-foreground text-sm">Loading templates...</p>
                    <p v-else-if="templatesError" class="text-destructive text-sm">{{ templatesError }}</p>
                    <p v-else-if="filteredTemplates.length === 0" class="text-muted-foreground text-sm">
                        No templates match your filters.
                    </p>

                    <div v-else class="max-h-[420px] overflow-y-auto pr-1">
                        <div class="grid gap-3 md:grid-cols-3">
                            <button
                                v-for="template in filteredTemplates"
                                :key="template.id"
                                type="button"
                                class="rounded-xl border p-3 text-left transition-colors"
                                :class="templateDisableReason(template) ? 'cursor-not-allowed border-border/60 opacity-60' : 'border-border hover:bg-muted/40'"
                                :disabled="Boolean(templateDisableReason(template))"
                                @click="applyTemplate(template)"
                            >
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ template.niche }}
                                </p>
                                <h3 class="mt-1 text-sm font-semibold">{{ template.name }}</h3>
                                <p class="text-muted-foreground mt-1 line-clamp-2 text-xs">
                                    {{ template.description }}
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                    <span class="rounded-full border px-2 py-0.5">{{ mediaProfileLabel(template.media_profile) }}</span>
                                    <span class="rounded-full border px-2 py-0.5">{{ template.page_count }} pages</span>
                                    <span class="rounded-full border px-2 py-0.5">{{ estimateTemplateCredits(template) }} credits</span>
                                </div>

                                <p v-if="templateDisableReason(template)" class="text-destructive mt-2 text-xs">
                                    {{ templateDisableReason(template) }}
                                </p>
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
