<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Pencil, Save, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

export type QuizRow = {
    question: string;
    choices: string[];
    answer: string;
};

const props = withDefaults(
    defineProps<{
        storyUuid: string;
        pageUuid: string;
        questions: QuizRow[];
        editable?: boolean;
    }>(),
    { editable: false },
);

const local = ref<QuizRow[]>([]);
const saving = ref(false);
const picked = ref<Record<number, string | null>>({});

watch(
    () => props.questions,
    (q) => {
        local.value = q.map((row) => ({
            question: row.question,
            choices: [...row.choices],
            answer: row.answer,
        }));
        picked.value = {};
    },
    { immediate: true, deep: true },
);

const dirty = computed(() => JSON.stringify(local.value) !== JSON.stringify(props.questions));

/** Rows used for tap-to-answer preview (draft when editing). */
const playRows = computed(() => (props.editable ? local.value : props.questions));

function addChoice(qi: number): void {
    const row = local.value[qi];

    if (!row) {
        return;
    }

    row.choices.push('');
}

function removeChoice(qi: number, ci: number): void {
    const row = local.value[qi];

    if (!row || row.choices.length <= 2) {
        return;
    }

    row.choices.splice(ci, 1);
}

function pickAnswer(qi: number, choice: string): void {
    picked.value = { ...picked.value, [qi]: choice };
}

function isCorrect(qi: number, choice: string): boolean {
    const row = playRows.value[qi];

    if (!row) {
        return false;
    }

    return choice.trim().toLowerCase() === row.answer.trim().toLowerCase();
}

function saveEdits(): void {
    if (!props.storyUuid || !props.pageUuid) {
        return;
    }

    const payload = local.value.map((row) => ({
        question: row.question.trim(),
        choices: row.choices.map((c) => c.trim()).filter(Boolean),
        answer: row.answer.trim(),
    }));
    saving.value = true;
    router.patch(
        `/stories/${props.storyUuid}/pages/${props.pageUuid}`,
        { quiz_questions: payload },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function optionClass(qi: number, opt: string): string {
    const p = picked.value[qi];

    if (p === undefined || p === null) {
        return 'border-border/80 bg-background hover:border-primary/40 hover:bg-primary/5';
    }

    if (p !== opt) {
        return 'border-border/40 bg-muted/30 opacity-60';
    }

    return isCorrect(qi, opt) ? 'border-emerald-500 bg-emerald-500/10' : 'border-amber-500 bg-amber-500/10';
}
</script>

<template>
    <div class="story-quiz-sheet flex h-full flex-col gap-3 overflow-y-auto p-4 sm:p-5">
        <div class="flex items-center gap-2">
            <span class="text-primary inline-flex size-9 items-center justify-center rounded-full bg-primary/15">
                <Pencil v-if="editable" class="size-4" />
                <span v-else class="text-lg font-bold">?</span>
            </span>
            <div>
                <p class="text-sm font-semibold tracking-tight">Story quiz</p>
                <p class="text-muted-foreground text-xs">Tap the best answer — see if you got it right!</p>
            </div>
        </div>

        <div v-if="editable" class="space-y-4 border-b border-border/60 pb-4">
            <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">Edit (setup)</p>
            <div v-for="(row, qi) in local" :key="qi" class="space-y-2 rounded-xl border border-border/70 bg-muted/20 p-3">
                <label class="block text-xs font-medium">Question {{ qi + 1 }}</label>
                <textarea
                    v-model="row.question"
                    rows="2"
                    class="border-input bg-background w-full rounded-md border px-2 py-1.5 text-sm"
                />
                <p class="text-xs font-medium">Choices</p>
                <div v-for="(_c, ci) in row.choices" :key="ci" class="flex gap-1">
                    <input
                        v-model="row.choices[ci]"
                        type="text"
                        class="border-input bg-background min-w-0 flex-1 rounded-md border px-2 py-1 text-sm"
                    />
                    <Button
                        v-if="row.choices.length > 2"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="shrink-0"
                        @click="removeChoice(qi, ci)"
                    >
                        ×
                    </Button>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addChoice(qi)">Add choice</Button>
                <label class="mt-2 block text-xs font-medium">Correct answer (exact match to a choice)</label>
                <input
                    v-model="row.answer"
                    type="text"
                    class="border-input bg-background w-full rounded-md border px-2 py-1 text-sm"
                />
            </div>
            <Button type="button" size="sm" :disabled="!dirty || saving" @click="saveEdits">
                <Save class="mr-1 size-4" />
                Save quiz
            </Button>
        </div>

        <div class="flex flex-1 flex-col gap-5">
            <div
                v-for="(row, qi) in playRows"
                :key="'play-' + qi"
                class="rounded-2xl border-2 border-primary/20 bg-gradient-to-br from-card to-primary/5 p-4 shadow-sm"
            >
                <p class="mb-3 text-base font-semibold leading-snug text-foreground">{{ row.question }}</p>
                <div class="flex flex-col gap-2">
                    <button
                        v-for="(opt, oi) in row.choices"
                        :key="oi"
                        type="button"
                        class="quiz-option flex min-h-12 items-center justify-between gap-3 rounded-xl border-2 px-4 py-3 text-left text-sm font-medium transition-all"
                        :class="optionClass(qi, opt)"
                        @click="pickAnswer(qi, opt)"
                    >
                        <span>{{ opt }}</span>
                        <CheckCircle2 v-if="picked[qi] === opt && isCorrect(qi, opt)" class="size-6 shrink-0 text-emerald-600" />
                        <XCircle v-else-if="picked[qi] === opt" class="size-6 shrink-0 text-amber-600" />
                    </button>
                </div>
                <p
                    v-if="picked[qi] !== undefined && picked[qi] !== null"
                    class="mt-3 text-center text-sm font-semibold"
                    :class="isCorrect(qi, picked[qi]!) ? 'text-emerald-600' : 'text-amber-700 dark:text-amber-500'"
                >
                    {{ isCorrect(qi, picked[qi]!) ? 'Great job! That is correct.' : 'Nice try — pick another answer!' }}
                </p>
            </div>
        </div>
    </div>
</template>
