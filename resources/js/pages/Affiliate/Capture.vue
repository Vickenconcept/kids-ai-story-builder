<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

type Partner = {
    name: string;
    slug: string;
};

const props = defineProps<{
    partner: Partner;
}>();

const form = useForm({
    name: '',
    email: '',
});

const submit = () => {
    form.post(`/affiliate/${props.partner.slug}/lead`);
};
</script>

<template>
    <Head :title="`Special Access — DreamForge AI`" />

    <!-- HERO -->
    <section class="relative overflow-hidden bg-linear-to-br from-violet-950 via-indigo-950 to-purple-950 text-white">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 left-1/2 size-[700px] -translate-x-1/2 rounded-full bg-violet-600/25 blur-[120px]" />
            <div class="absolute top-20 right-[-5%] size-[400px] rounded-full bg-fuchsia-600/15 blur-[100px]" />
            <div class="absolute bottom-0 left-[-5%] size-[350px] rounded-full bg-indigo-600/20 blur-[100px]" />
        </div>

        <div class="relative mx-auto max-w-6xl px-4 pt-12 pb-10 md:px-8 md:pt-20">
            <div class="mb-6 flex items-center justify-center gap-3">
                <img src="/images/logo-without-bg.png" alt="DreamForge AI" class="h-10 w-auto object-contain" />
                <span class="text-lg font-bold tracking-wide text-white">DreamForge AI</span>
            </div>

            <div class="grid gap-10 lg:grid-cols-2 lg:items-start">
                <!-- Left: pitch -->
                <div>
                    <p class="inline-flex items-center gap-2 rounded-full border border-fuchsia-400/30 bg-fuchsia-400/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-fuchsia-300">
                        <span class="size-1.5 animate-pulse rounded-full bg-fuchsia-400" />
                        Exclusive access — via {{ partner.name }}
                    </p>

                    <h1 class="mt-5 text-3xl font-extrabold leading-tight md:text-5xl">
                        Turn a Simple Idea Into a
                        <span class="bg-linear-to-r from-fuchsia-400 via-violet-400 to-indigo-400 bg-clip-text text-transparent">
                            Complete AI Storybook
                        </span>
                        in Minutes
                    </h1>

                    <p class="mt-4 text-base text-violet-200">
                        DreamForge AI creates full kids storybooks — pages, matching illustrations, read-aloud narration,
                        interactive flipbook and comprehension quizzes — from one idea. No writing, no design, no recording.
                    </p>

                    <ul class="mt-6 space-y-2 text-sm text-violet-200">
                        <li class="flex items-center gap-2"><span class="text-fuchsia-400">✓</span> Multi-page story generated instantly</li>
                        <li class="flex items-center gap-2"><span class="text-fuchsia-400">✓</span> AI illustrations matched to each page</li>
                        <li class="flex items-center gap-2"><span class="text-fuchsia-400">✓</span> Read-aloud voice narration built in</li>
                        <li class="flex items-center gap-2"><span class="text-fuchsia-400">✓</span> Interactive flipbook reader with quizzes</li>
                        <li class="flex items-center gap-2"><span class="text-fuchsia-400">✓</span> Optional page videos</li>
                        <li class="flex items-center gap-2"><span class="text-fuchsia-400">✓</span> KDP-ready export for Amazon publishing</li>
                    </ul>

                    <!-- Flipbook mock -->
                    <div class="mt-8 overflow-hidden rounded-2xl border border-violet-400/20 bg-linear-to-br from-violet-900/60 to-indigo-900/60 shadow-2xl shadow-violet-950/80">
                        <div class="flex items-center gap-2 border-b border-white/8 bg-white/4 px-4 py-3">
                            <span class="size-3 rounded-full bg-red-500/70" />
                            <span class="size-3 rounded-full bg-yellow-500/70" />
                            <span class="size-3 rounded-full bg-green-500/70" />
                            <div class="ml-3 flex-1 rounded-md bg-violet-900/60 px-3 py-1 text-left text-[11px] text-violet-400/60">
                                dreamforge.ai/read/the-dragon-who-found-a-friend
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-0">
                            <div class="flex flex-col gap-3 border-r border-white/8 p-5">
                                <div class="aspect-4/3 w-full overflow-hidden rounded-xl">
                                    <img src="/images/card-kids-image.png" alt="Story illustration preview" class="h-full w-full object-cover" loading="lazy" />
                                </div>
                                <div class="space-y-1.5">
                                    <div class="h-2 w-3/4 rounded bg-white/10" />
                                    <div class="h-2 w-full rounded bg-white/10" />
                                    <div class="h-2 w-5/6 rounded bg-white/10" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-3 p-5">
                                <div class="aspect-4/3 w-full overflow-hidden rounded-xl">
                                    <img src="/images/banner-childern-image.png" alt="Story page illustration" class="h-full w-full object-cover" loading="lazy" />
                                </div>
                                <div class="space-y-1.5">
                                    <div class="h-2 w-full rounded bg-white/10" />
                                    <div class="h-2 w-4/5 rounded bg-white/10" />
                                    <div class="h-2 w-full rounded bg-white/10" />
                                </div>
                                <div class="rounded-lg border border-yellow-400/20 bg-yellow-400/5 p-2">
                                    <p class="text-[10px] font-semibold text-yellow-300">Quiz</p>
                                    <div class="mt-1 space-y-1">
                                        <div class="h-1.5 w-4/5 rounded bg-white/10" />
                                        <div class="h-1.5 w-3/5 rounded bg-white/10" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/8 bg-white/2 px-4 py-2 text-[11px] text-violet-400/60">
                            <span>Page 4 of 12</span>
                            <div class="flex items-center gap-3">
                                <span class="text-fuchsia-400/70">Illustrated</span>
                                <span class="text-green-400/70">Narrated</span>
                                <span class="text-indigo-400/70">Quiz</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: opt-in form -->
                <div class="lg:sticky lg:top-8">
                    <div class="rounded-2xl border border-violet-400/20 bg-white/5 p-6 shadow-2xl shadow-violet-950/60 backdrop-blur-sm md:p-8">
                        <p class="text-xs font-semibold uppercase tracking-widest text-fuchsia-300">Get Instant Access</p>
                        <h2 class="mt-2 text-2xl font-extrabold text-white">
                            Enter your email to unlock the full offer
                        </h2>
                        <p class="mt-2 text-sm text-violet-300">No commitment. See the full demo and special launch pricing immediately after.</p>

                        <form class="mt-6 space-y-4" @submit.prevent="submit">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-violet-300" for="af-name">
                                    First name (optional)
                                </label>
                                <input
                                    id="af-name"
                                    v-model="form.name"
                                    type="text"
                                    maxlength="120"
                                    placeholder="Your name"
                                    class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-violet-400/60 outline-none ring-fuchsia-400 transition focus:border-fuchsia-400/60 focus:ring-2"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-violet-300" for="af-email">
                                    Email address <span class="text-fuchsia-400">*</span>
                                </label>
                                <input
                                    id="af-email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    maxlength="190"
                                    placeholder="you@example.com"
                                    class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-violet-400/60 outline-none ring-fuchsia-400 transition focus:border-fuchsia-400/60 focus:ring-2"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                            </div>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-linear-to-r from-fuchsia-500 to-violet-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-900/50 transition hover:from-fuchsia-400 hover:to-violet-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                {{ form.processing ? 'Redirecting...' : 'Show Me the Offer →' }}
                            </button>
                        </form>

                        <div class="mt-6 space-y-2 text-xs text-violet-400">
                            <p class="flex items-center gap-1.5"><span class="text-fuchsia-400">✓</span> One-time payment — no monthly fees</p>
                            <p class="flex items-center gap-1.5"><span class="text-fuchsia-400">✓</span> Instant access after purchase</p>
                            <p class="flex items-center gap-1.5"><span class="text-fuchsia-400">✓</span> Works for teachers, parents, and creators</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trust strip -->
        <div class="relative border-t border-white/10 bg-white/5">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-6 px-4 py-5 text-xs font-medium text-violet-300">
                <span class="flex items-center gap-1.5"><span class="text-fuchsia-400">✓</span> Full story generation from a single prompt</span>
                <span class="flex items-center gap-1.5"><span class="text-fuchsia-400">✓</span> AI illustrations per page</span>
                <span class="flex items-center gap-1.5"><span class="text-fuchsia-400">✓</span> Built-in narration and quizzes</span>
                <span class="flex items-center gap-1.5"><span class="text-fuchsia-400">✓</span> Classroom and home-ready</span>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="bg-linear-to-b from-indigo-950 to-violet-950 px-4 py-16 text-white md:py-24">
        <div class="mx-auto max-w-5xl text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-fuchsia-300">How It Works</p>
            <h2 class="mt-3 text-3xl font-extrabold md:text-4xl">From Idea to Published Storybook in 3 Steps</h2>
        </div>
        <div class="mx-auto mt-10 grid max-w-5xl gap-6 md:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                <p class="text-4xl font-black text-violet-500/30">01</p>
                <h3 class="mt-3 text-base font-bold">Enter Your Idea</h3>
                <p class="mt-2 text-sm leading-relaxed text-violet-300">Type a story idea, pick age group and style. The AI handles writing, structure and length.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                <p class="text-4xl font-black text-violet-500/30">02</p>
                <h3 class="mt-3 text-base font-bold">AI Builds Everything</h3>
                <p class="mt-2 text-sm leading-relaxed text-violet-300">Pages, illustrations, narration, quizzes and optional videos are generated automatically.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                <p class="text-4xl font-black text-violet-500/30">03</p>
                <h3 class="mt-3 text-base font-bold">Read, Share, or Publish</h3>
                <p class="mt-2 text-sm leading-relaxed text-violet-300">Share your interactive flipbook link, or export a KDP-ready PDF and publish on Amazon.</p>
            </div>
        </div>
    </section>

    <!-- BOTTOM CTA -->
    <section class="bg-violet-950 px-4 py-16 text-center text-white">
        <p class="text-xs font-semibold uppercase tracking-widest text-fuchsia-300">Special Launch Access</p>
        <h2 class="mt-3 text-3xl font-extrabold md:text-4xl">Ready to Create Your First Storybook?</h2>
        <p class="mx-auto mt-3 max-w-md text-sm text-violet-300">Enter your email above to unlock the full offer page and launch pricing.</p>
        <button
            type="button"
            class="mt-8 inline-flex items-center gap-2 rounded-full bg-linear-to-r from-fuchsia-500 to-violet-600 px-8 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-900/50 transition hover:from-fuchsia-400 hover:to-violet-500"
            @click="document.getElementById('af-email')?.focus()"
        >
            Get Instant Access →
        </button>
    </section>

    <footer class="bg-black px-4 py-6 text-center text-xs text-white/40">
        © 2026 DreamForge AI. All rights reserved.
    </footer>
</template>
