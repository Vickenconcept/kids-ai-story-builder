export type StoryQuizQuestion = {
    question: string;
    choices: string[];
    answer: string;
};

/** Normalize persisted `quiz_questions` (array or JSON string) into rows for readers and editors. */
export function normalizeStoryQuizQuestions(raw: unknown): StoryQuizQuestion[] {
    let arr: unknown[] = [];

    if (Array.isArray(raw)) {
        arr = raw;
    } else if (typeof raw === 'string') {
        try {
            const parsed = JSON.parse(raw) as unknown;

            if (Array.isArray(parsed)) {
                arr = parsed;
            }
        } catch {
            arr = [];
        }
    }

    return arr
        .map((item) => {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const o = item as Record<string, unknown>;

            return {
                question: String(o.question ?? '').trim(),
                choices: Array.isArray(o.choices) ? o.choices.map((c) => String(c)) : [],
                answer: String(o.answer ?? '').trim(),
            };
        })
        .filter((row): row is StoryQuizQuestion => Boolean(row && row.question));
}
