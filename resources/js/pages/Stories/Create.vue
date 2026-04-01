<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    featureTier: string;
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
    if (form.page_count > 15) {
        form.page_count = 15;
    }
}

const isPro = props.featureTier === 'pro';

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
                        <span>Include narration (TTS)</span>
                        <span class="relative inline-flex">
                            <input v-model="form.include_narration" type="checkbox" class="peer sr-only" />
                            <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                            </span>
                        </span>
                    </label>
                    <label v-if="isPro" class="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border/60 p-2.5 transition-colors">
                        <span>Include page video (Pro)</span>
                        <span class="relative inline-flex">
                            <input v-model="form.include_video" type="checkbox" class="peer sr-only" />
                            <span class="bg-muted peer-checked:bg-primary/80 inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                <span class="bg-background ml-0.5 size-5 rounded-full transition-transform peer-checked:translate-x-5" />
                            </span>
                        </span>
                    </label>
                    <p v-else class="text-muted-foreground text-xs">
                        Video generation is available on the Pro tier.
                    </p>
                </div>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing">Generate</Button>
                    <Button variant="outline" type="button" as-child>
                        <Link href="/stories">Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
