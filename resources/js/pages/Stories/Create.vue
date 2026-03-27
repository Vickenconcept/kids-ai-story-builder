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

const isPro = props.featureTier === 'pro';
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
                        <Input id="lesson_type" v-model="form.lesson_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="age_group">Age group</Label>
                        <Input id="age_group" v-model="form.age_group" />
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
                            max="32"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="illustration_style">Illustration style</Label>
                        <Input id="illustration_style" v-model="form.illustration_style" />
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-lg border p-4">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            v-model="form.include_quiz"
                            type="checkbox"
                            class="size-4 rounded border"
                        />
                        <span>Include quizzes (per page)</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            v-model="form.include_narration"
                            type="checkbox"
                            class="size-4 rounded border"
                        />
                        <span>Include narration (TTS)</span>
                    </label>
                    <label v-if="isPro" class="flex cursor-pointer items-center gap-2">
                        <input
                            v-model="form.include_video"
                            type="checkbox"
                            class="size-4 rounded border"
                        />
                        <span>Include page video (Pro)</span>
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
