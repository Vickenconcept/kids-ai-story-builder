<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

const viewMode = computed<'flip' | 'scroll'>(() =>
    props.project.flip_settings?.readMode === 'scroll' ? 'scroll' : 'flip',
);
const carouselIndex = ref(0);

const carouselPage = computed(() => props.pages[carouselIndex.value] ?? null);

const canCarouselPrev = computed(() => carouselIndex.value > 0);
const canCarouselNext = computed(() => carouselIndex.value < props.pages.length - 1);

function goCarouselPrev(): void {
    if (!canCarouselPrev.value) {
        return;
    }

    carouselIndex.value -= 1;
}

function goCarouselNext(): void {
    if (!canCarouselNext.value) {
        return;
    }

    carouselIndex.value += 1;
}

watch(
    () => props.pages.length,
    () => {
        if (carouselIndex.value > props.pages.length - 1) {
            carouselIndex.value = Math.max(0, props.pages.length - 1);
        }
    },
);

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

            <div v-if="viewMode === 'scroll'" class="space-y-4">
                <div class="flex items-center justify-center gap-2">
                    <Button type="button" variant="outline" size="sm" :disabled="!canCarouselPrev" @click="goCarouselPrev">
                        Previous
                    </Button>
                    <p class="text-muted-foreground text-xs">
                        <span v-if="carouselPage">Page {{ carouselPage.page_number }} of {{ pages.length }}</span>
                        <span v-else>No pages yet</span>
                    </p>
                    <Button type="button" variant="outline" size="sm" :disabled="!canCarouselNext" @click="goCarouselNext">
                        Next
                    </Button>
                </div>

                <article
                    v-if="carouselPage"
                    :key="carouselPage.uuid"
                    class="mx-auto rounded-xl border border-sidebar-border/70 p-4 transition-all duration-300 dark:border-sidebar-border"
                >
                    <h2 class="mb-3 text-lg font-medium">Page {{ carouselPage.page_number }}</h2>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <p class="text-sm leading-relaxed">{{ carouselPage.text_content }}</p>
                        <div v-if="carouselPage.video_url" class="overflow-hidden rounded-lg border bg-black">
                            <video
                                :src="carouselPage.video_url"
                                controls
                                playsinline
                                class="max-h-80 w-full object-contain"
                            />
                        </div>
                        <div v-else-if="carouselPage.image_url" class="overflow-hidden rounded-lg border">
                            <img
                                :src="carouselPage.image_url"
                                :alt="`Illustration page ${carouselPage.page_number}`"
                                draggable="false"
                                class="max-h-80 w-full select-none object-contain [-webkit-user-drag:none]"
                            />
                        </div>
                    </div>
                    <audio v-if="carouselPage.audio_url" :src="carouselPage.audio_url" controls class="mt-4 w-full" />
                </article>

                <p v-else class="text-muted-foreground text-center text-sm">
                    This story has no pages yet.
                </p>
            </div>
        </main>
    </div>
</template>
