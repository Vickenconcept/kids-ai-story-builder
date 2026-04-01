<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StoryFlipbook from '@/components/StoryFlipbook.vue';
import type {CoverConfigJson} from '@/components/StoryFlipbook.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home, login } from '@/routes';

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
        uuid: string;
        title: string;
        topic: string;
        include_quiz: boolean;
        include_narration: boolean;
        flip_gameplay_enabled: boolean;
        cover_front: CoverConfigJson;
        cover_back: CoverConfigJson;
        flip_settings: Record<string, unknown> | null;
    };
    pages: PageRow[];
}>();

const viewMode = ref<'flip' | 'scroll'>('flip');

const flipbookKey = computed(() =>
    [
        props.project.uuid,
        props.project.flip_gameplay_enabled,
        JSON.stringify(props.project.cover_front),
        JSON.stringify(props.project.cover_back),
        JSON.stringify(props.project.flip_settings),
        props.pages.length,
        props.pages.map((p) => p.uuid).join('-'),
    ].join('|'),
);
</script>

<template>
    <Head :title="project.title" />

    <div class="bg-background min-h-screen">
        <header class="border-border flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
            <Link
                :href="home()"
                class="text-muted-foreground hover:text-foreground text-sm font-medium transition-colors"
            >
                ← {{ $page.props.name }}
            </Link>
            <div class="flex items-center gap-2">
                <Button v-if="$page.props.auth?.user" variant="ghost" size="sm" as-child>
                    <Link :href="dashboard()">Dashboard</Link>
                </Button>
                <Button v-else variant="ghost" size="sm" as-child>
                    <Link :href="login()">Log in</Link>
                </Button>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-semibold tracking-tight">{{ project.title }}</h1>
                <p class="text-muted-foreground mt-2 text-sm">{{ project.topic }}</p>
            </div>

            <div
                class="bg-muted/40 mx-auto mb-6 flex max-w-md rounded-lg border border-border p-0.5 text-xs font-medium"
                role="group"
                aria-label="View mode"
            >
                <button
                    type="button"
                    class="flex-1 rounded-md px-3 py-2 transition-colors"
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
                    class="flex-1 rounded-md px-3 py-2 transition-colors"
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

            <div v-if="viewMode === 'flip' && pages.length > 0" class="w-full">
                <StoryFlipbook
                    :key="flipbookKey"
                    :title="project.title"
                    :pages="pages"
                    :play-audio-on-flip="project.include_narration"
                    :story-uuid="project.uuid"
                    :include-quiz="project.include_quiz"
                    :gameplay-enabled="project.flip_gameplay_enabled"
                    :setup-mode="false"
                    :cover-front="project.cover_front"
                    :cover-back="project.cover_back"
                    :flip-settings="project.flip_settings"
                />
            </div>
            <p
                v-else-if="viewMode === 'flip' && pages.length === 0"
                class="text-muted-foreground text-center text-sm"
            >
                This story has no pages yet.
            </p>

            <div v-if="viewMode === 'scroll'" class="flex flex-col gap-8">
                <article
                    v-for="page in pages"
                    :key="page.uuid"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="mb-3 text-lg font-medium">Page {{ page.page_number }}</h2>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <p class="text-sm leading-relaxed">{{ page.text_content }}</p>
                        <div v-if="page.image_url" class="overflow-hidden rounded-lg border">
                            <img
                                :src="page.image_url"
                                :alt="`Illustration page ${page.page_number}`"
                                draggable="false"
                                class="max-h-80 w-full select-none object-contain [-webkit-user-drag:none]"
                            />
                        </div>
                    </div>
                    <audio v-if="page.audio_url" :src="page.audio_url" controls class="mt-4 w-full" />
                </article>
            </div>
        </main>
    </div>
</template>
