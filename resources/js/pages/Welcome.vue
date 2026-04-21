<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

withDefaults(
    defineProps<{
        canRegister: boolean;
        plans?: Array<{
            id: number;
            name: string;
            description: string | null;
            tier: 'basic' | 'pro' | 'elite';
            included_credits: number;
            price_cents: number;
            currency: string;
            is_featured: boolean;
            feature_list: string[];
        }>;
    }>(),
    {
        canRegister: true,
        plans: () => [],
    },
);

const formatPrice = (amountCents: number, currency: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(amountCents / 100);

const tierSummary = (tier: 'basic' | 'pro' | 'elite') => {
    if (tier === 'basic') {
        return [
            'Create a few storybooks',
            'Perfect for trying with your child or class',
            'Great for first-time users',
        ];
    }

    if (tier === 'pro') {
        return [
            'Create more storybooks each month',
            'Ideal for regular home or classroom use',
            'Better fit for weekly storytelling activities',
        ];
    }

    return [
        'Higher usage for teachers and schools',
        'Designed for multiple students',
        'Best for ongoing classroom storytelling programs',
    ];
};

const features = [
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>`,
        title: 'Full Storybooks',
        desc: 'Children start with one idea and get a complete multi-page storybook in seconds.',
        color: 'violet',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`,
        title: 'Illustrated Pages',
        desc: 'Each page includes matching visuals to keep students and young readers engaged.',
        color: 'indigo',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M12 18.5a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13z"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg>`,
        title: 'Read-Aloud Narration',
        desc: 'Built-in narration supports early readers, ESL learners, and at-home practice.',
        color: 'purple',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="m22 8-6 4 6 4V8z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/></svg>`,
        title: 'Optional Page Videos',
        desc: 'Turn pages into short visuals to bring stories to life during class or home reading.',
        color: 'pink',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>`,
        title: 'Interactive Flipbook',
        desc: 'A child-friendly reading experience that makes stories fun to read and share.',
        color: 'violet',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`,
        title: 'Comprehension Quizzes',
        desc: 'Each story can include quizzes to reinforce understanding after reading.',
        color: 'indigo',
    },
];

const steps = [
    { num: '01', title: 'Enter an Idea', desc: 'A child, student, teacher, or parent enters a simple story idea and reading level.' },
    { num: '02', title: 'AI Builds the Storybook', desc: 'Pages, illustrations, narration, and quiz content are generated automatically.' },
    { num: '03', title: 'Read, Listen, and Share', desc: 'Children read and listen to their stories, then share them with class or family.' },
];

const faqs = [
    {
        question: 'Do I need writing or design experience?',
        answer: 'No. DreamForge AI is made for beginners. You enter your story idea and the app generates the pages, visuals, and structure for you.',
    },
    {
        question: 'Can schools and parents both use it?',
        answer: 'Yes. DreamForge AI is designed for classrooms, homeschooling, and family learning at home.',
    },
    {
        question: 'How fast does a storybook get generated?',
        answer: 'Most books are generated in minutes, depending on page count and media options like narration or video.',
    },
    {
        question: 'Can I upgrade my plan later?',
        answer: 'Yes. You can start with Basic and upgrade to Pro or Elite anytime from your account.',
    },
    {
        question: 'Can I share the story with others?',
        answer: 'Yes. You can share interactive story links with students, parents, and family members.',
    },
    {
        question: 'Is there a free option to try first?',
        answer: 'Yes. Starter gives you free access so you can try the workflow before upgrading.',
    },
];

const landingRoot = ref<HTMLElement | null>(null);

onMounted(async () => {
    await nextTick();

    if (typeof window === 'undefined' || !landingRoot.value) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    gsap.registerPlugin(ScrollTrigger);

    const heroBadge = landingRoot.value.querySelector('.js-hero-badge');
    const heroTitle = landingRoot.value.querySelector('.js-hero-title');
    const heroSub = landingRoot.value.querySelector('.js-hero-sub');
    const heroCtas = landingRoot.value.querySelector('.js-hero-ctas');
    const heroPreview = landingRoot.value.querySelector('.js-hero-preview');
    const trustStrip = landingRoot.value.querySelector('.js-trust-strip');

    const intro = gsap.timeline({ defaults: { ease: 'power3.out' } });
    intro
        .from('.js-nav', { y: -18, opacity: 0, duration: 0.5 })
        .from(heroBadge, { y: 16, opacity: 0, duration: 0.45 }, '-=0.2')
        .from(heroTitle, { y: 30, opacity: 0, duration: 0.7 }, '-=0.18')
        .from(heroSub, { y: 20, opacity: 0, duration: 0.55 }, '-=0.35')
        .from(heroCtas, { y: 16, opacity: 0, duration: 0.45 }, '-=0.35')
        .from(heroPreview, { y: 35, opacity: 0, scale: 0.97, duration: 0.8 }, '-=0.2')
        .from(trustStrip, { y: 18, opacity: 0, duration: 0.5 }, '-=0.35');

    const sections = landingRoot.value.querySelectorAll('.js-reveal');
    sections.forEach((section) => {
        gsap.fromTo(
            section,
            { y: 28, autoAlpha: 0.01 },
            {
                y: 0,
                autoAlpha: 1,
                duration: 0.7,
                ease: 'power3.out',
                immediateRender: false,
                scrollTrigger: {
                    trigger: section,
                    start: 'top 88%',
                    once: true,
                },
            },
        );
    });

    const staggerGroups = landingRoot.value.querySelectorAll('.js-stagger-group');
    staggerGroups.forEach((group) => {
        const cards = group.querySelectorAll('.js-stagger-item');
        if (!cards.length) return;
        gsap.fromTo(
            cards,
            { y: 20, autoAlpha: 0.01 },
            {
                y: 0,
                autoAlpha: 1,
                duration: 0.5,
                stagger: 0.08,
                ease: 'power2.out',
                immediateRender: false,
                scrollTrigger: {
                    trigger: group,
                    start: 'top 88%',
                    once: true,
                },
            },
        );
    });

    ScrollTrigger.refresh();
});

onBeforeUnmount(() => {
    ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
});
</script>

<template>
    <Head title="DreamForge AI — Turn Kids Into Published Storytellers">
        <meta name="description" content="DreamForge AI helps children create illustrated storybooks with narration and quizzes in minutes. Built for schools and parents. Free to start." />
    </Head>

    <div ref="landingRoot" class="min-h-screen scroll-smooth bg-slate-50 text-slate-900 antialiased dark:bg-[#0d0d1a] dark:text-white">

        <!-- ── NAV ─────────────────────────────────────────────── -->
        <header class="js-nav sticky top-0 z-50 border-b border-slate-200 bg-white/85 backdrop-blur-xl dark:border-white/6 dark:bg-[#0d0d1a]/80">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                <!-- Logo -->
                <div class="flex items-center">
                    <img src="/images/logo-without-bg.png" alt="DreamForge AI" class="h-16 w-auto object-contain" />
                </div>

                <!-- Nav + auth links -->
                <nav class="flex items-center gap-2">
                    <a
                        href="#how-it-works"
                        class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 md:inline-flex dark:text-slate-300 dark:hover:text-white"
                    >
                        How it works
                    </a>
                    <a
                        href="#pricing"
                        class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 md:inline-flex dark:text-slate-300 dark:hover:text-white"
                    >
                        Pricing
                    </a>
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="dashboard()"
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold transition hover:bg-violet-500"
                        >
                            Go to Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold transition hover:bg-violet-500"
                        >
                            Get Started Free
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- ── HERO ───────────────────────────────────────────── -->
        <section class="relative overflow-hidden px-5 pb-24 pt-20 text-center md:pb-32 md:pt-28">
            <!-- Glow orbs -->
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute -top-40 left-1/2 size-[700px] -translate-x-1/2 rounded-full bg-violet-700/20 blur-[120px]" />
                <div class="absolute top-20 right-[-10%] size-[400px] rounded-full bg-indigo-600/15 blur-[100px]" />
                <div class="absolute bottom-0 left-[-5%] size-[350px] rounded-full bg-purple-600/15 blur-[100px]" />
            </div>

            <div class="relative mx-auto max-w-4xl">
                <!-- Badge -->
                <span class="js-hero-badge mb-6 inline-flex items-center gap-2 rounded-full border border-violet-300 bg-violet-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-300">
                    <span class="size-1.5 animate-pulse rounded-full bg-violet-400" />
                    Built for classrooms and homes
                </span>

                <!-- Headline -->
                <h1 class="js-hero-title mt-4 text-4xl font-extrabold leading-[1.1] tracking-tight md:text-6xl lg:text-7xl">
                    From Idea to Illustrated Storybook in Minutes
                    <span class="bg-linear-to-r from-violet-400 via-purple-400 to-indigo-400 bg-clip-text text-transparent">
                        Built for Classrooms and Homes
                    </span>
                </h1>

                <!-- Sub -->
                <p class="js-hero-sub mx-auto mt-6 max-w-2xl text-base leading-relaxed text-slate-600 md:text-lg dark:text-slate-400">
                    DreamForge AI helps students and children create fully illustrated storybooks with narration and built-in comprehension quizzes in one simple workflow.
                    Build creativity, reading confidence, and comprehension without complicated setup.
                </p>

                <!-- CTAs -->
                <div class="js-hero-ctas mt-8 flex flex-wrap justify-center gap-3">
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="register()"
                        class="inline-flex items-center gap-2 rounded-full bg-violet-600 px-7 py-3.5 text-sm font-bold shadow-lg shadow-violet-600/30 transition hover:bg-violet-500 hover:shadow-violet-500/40 active:scale-95"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                        Create Your First Storybook Free
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-full bg-violet-600 px-7 py-3.5 text-sm font-bold shadow-lg shadow-violet-600/30 transition hover:bg-violet-500 active:scale-95"
                    >
                        Open Dashboard →
                    </Link>
                    <a
                        href="#how-it-works"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-7 py-3.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-white/15 dark:text-slate-300 dark:hover:border-white/30 dark:hover:text-white"
                    >
                        See How It Works
                    </a>
                </div>
                <p class="mt-4 text-xs text-slate-600">No setup required &nbsp;·&nbsp; Safe for classrooms &nbsp;·&nbsp; Free to start</p>
            </div>

            <!-- Floating preview card -->
            <div class="js-hero-preview relative mx-auto mt-16 max-w-3xl motion-safe:transition-transform motion-safe:duration-500 motion-safe:hover:-translate-y-1">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-linear-to-br from-white to-slate-100 shadow-2xl shadow-violet-200/60 dark:border-white/8 dark:from-[#1a1a2e] dark:to-[#16213e] dark:shadow-violet-950/60">
                    <!-- Mock browser bar -->
                    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-100/80 px-4 py-3 dark:border-white/6 dark:bg-white/3">
                        <span class="size-3 rounded-full bg-red-500/60" />
                        <span class="size-3 rounded-full bg-yellow-500/60" />
                        <span class="size-3 rounded-full bg-green-500/60" />
                        <div class="ml-3 flex-1 rounded-md bg-slate-200/70 px-3 py-1 text-left text-[11px] text-slate-500 dark:bg-white/6">
                            dreamforge .ai/read/the-dragon-who-lost-his-fire
                        </div>
                    </div>
                    <!-- Mock flipbook -->
                    <div class="grid grid-cols-2 gap-0">
                        <!-- Left page -->
                        <div class="flex flex-col gap-3 border-r border-slate-200 bg-linear-to-br from-amber-50 to-orange-50 p-6 dark:border-white/6 dark:from-amber-50/5 dark:to-orange-50/5">
                            <div class="aspect-4/3 w-full rounded-xl bg-linear-to-br from-violet-200 to-indigo-200 flex items-center justify-center dark:from-violet-900/60 dark:to-indigo-900/60">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-10 text-violet-700/60 dark:text-violet-400/50" stroke-width="1">
                                    <rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                </svg>
                            </div>
                            <div class="space-y-2">
                                <div class="h-2.5 w-3/4 rounded bg-slate-300 dark:bg-white/10" />
                                <div class="h-2.5 w-full rounded bg-slate-300 dark:bg-white/10" />
                                <div class="h-2.5 w-5/6 rounded bg-slate-300 dark:bg-white/10" />
                                <div class="h-2.5 w-2/3 rounded bg-slate-300 dark:bg-white/10" />
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <div class="flex size-6 items-center justify-center rounded-full bg-violet-600/80 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-3"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-300 dark:bg-white/10">
                                    <div class="h-full w-1/3 rounded-full bg-violet-500" />
                                </div>
                            </div>
                        </div>
                        <!-- Right page -->
                        <div class="flex flex-col gap-3 bg-linear-to-br from-purple-50 to-violet-100 p-6 dark:from-purple-50/5 dark:to-violet-50/5">
                            <div class="aspect-4/3 w-full overflow-hidden rounded-xl bg-linear-to-br from-indigo-200 to-purple-200 flex items-center justify-center dark:from-indigo-900/60 dark:to-purple-900/60">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-10 text-indigo-700/60 dark:text-indigo-400/50" stroke-width="1">
                                    <path d="m22 8-6 4 6 4V8z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>
                                </svg>
                            </div>
                            <div class="space-y-2">
                                <div class="h-2.5 w-full rounded bg-slate-300 dark:bg-white/10" />
                                <div class="h-2.5 w-4/5 rounded bg-slate-300 dark:bg-white/10" />
                                <div class="h-2.5 w-full rounded bg-slate-300 dark:bg-white/10" />
                                <div class="h-2.5 w-3/4 rounded bg-slate-300 dark:bg-white/10" />
                            </div>
                            <div class="rounded-lg border border-yellow-300 bg-yellow-100 p-2 dark:border-yellow-400/20 dark:bg-yellow-400/5">
                                <p class="text-[10px] font-semibold text-yellow-700 dark:text-yellow-300/70">Quiz</p>
                                <div class="mt-1 space-y-1">
                                    <div class="h-2 w-4/5 rounded bg-slate-300 dark:bg-white/10" />
                                    <div class="h-2 w-3/5 rounded bg-slate-300 dark:bg-white/10" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Status bar -->
                    <div class="flex items-center justify-between border-t border-slate-200 bg-slate-100 px-4 py-2 text-[11px] text-slate-600 dark:border-white/6 dark:bg-white/2 dark:text-slate-500">
                        <span>Page 3 of 12</span>
                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-1 text-violet-700 dark:text-violet-400/70">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                Illustrated
                            </span>
                            <span class="flex items-center gap-1 text-green-400/70">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3"><path d="M12 18.5a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13z"/></svg>
                                Narrated
                            </span>
                            <span class="flex items-center gap-1 text-blue-400/70">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3"><path d="m22 8-6 4 6 4V8z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/></svg>
                                Video
                            </span>
                        </div>
                    </div>
                </div>
                <!-- Floating badge -->
            <div class="absolute -bottom-4 left-6 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-xl dark:border-white/10 dark:bg-[#1a1a2e]">
                    <span class="flex size-6 items-center justify-center rounded-full bg-green-500/20 text-green-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="size-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Story generated in 42 seconds</span>
                </div>
            <div class="absolute -bottom-4 right-6 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-xl dark:border-white/10 dark:bg-[#1a1a2e]">
                    <span class="text-base">📚</span>
                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">12 pages &nbsp;·&nbsp; Full illustrations</span>
                </div>
            </div>
        </section>

        <section class="js-reveal px-5 pb-8">
            <div class="mx-auto max-w-5xl rounded-2xl border border-violet-200 bg-white p-6 dark:border-violet-500/30 dark:bg-white/3">
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Real Classroom Example</p>
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight md:text-3xl">How Teachers Are Using DreamForge AI</h2>
                <div class="mt-4 grid gap-3 text-sm text-slate-700 dark:text-slate-300 md:grid-cols-2">
                    <p>A teacher gives the prompt: “Write a story about kindness.”</p>
                    <p>Students generate their own illustrated books in minutes.</p>
                    <p>They read, listen, and complete the comprehension quiz.</p>
                    <p>The teacher reviews understanding quickly and guides improvements.</p>
                </div>
            </div>
        </section>

        <!-- ── TRUST STRIP ─────────────────────────────────────── -->
        <div class="js-trust-strip border-y border-slate-200 bg-white dark:border-white/6 dark:bg-white/2">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-6 px-5 py-5 text-xs font-medium text-slate-700 dark:text-slate-500">
                <span class="flex items-center gap-1.5"><span class="text-violet-400">✓</span> Classroom-safe workflow</span>
                <span class="flex items-center gap-1.5"><span class="text-violet-400">✓</span> Story + visuals + narration</span>
                <span class="flex items-center gap-1.5"><span class="text-violet-400">✓</span> Built-in comprehension quizzes</span>
                <span class="flex items-center gap-1.5"><span class="text-violet-400">✓</span> Interactive read-aloud flipbook</span>
                <span class="flex items-center gap-1.5"><span class="text-violet-400">✓</span> Great for class and home learning</span>
            </div>
        </div>

        <section class="js-reveal px-5 py-20 md:py-24">
            <div class="mx-auto max-w-6xl">
                <div class="mb-12 text-center">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Who It Is For</p>
                    <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Built for Classrooms and Homes</h2>
                </div>
                <div class="js-stagger-group grid gap-5 md:grid-cols-3">
                    <article class="js-stagger-item rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/8 dark:bg-white/3">
                        <h3 class="text-lg font-bold">For Teachers</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li>Run engaging writing activities</li>
                            <li>Turn assignments into illustrated books</li>
                            <li>Assess comprehension with quizzes</li>
                        </ul>
                    </article>
                    <article class="js-stagger-item rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/8 dark:bg-white/3">
                        <h3 class="text-lg font-bold">For Parents</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li>Help your child create storybooks</li>
                            <li>Encourage reading with narration</li>
                            <li>Make learning fun and creative</li>
                        </ul>
                    </article>
                    <article class="js-stagger-item rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/8 dark:bg-white/3">
                        <h3 class="text-lg font-bold">For Schools</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li>Improve literacy engagement</li>
                            <li>Support different reading levels</li>
                            <li>Introduce creative digital learning</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        <!-- ── HOW IT WORKS ────────────────────────────────────── -->
        <section id="how-it-works" class="js-reveal px-5 py-20 md:py-28">
            <div class="mx-auto max-w-5xl">
                <div class="mb-14 text-center">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Simple Process</p>
                    <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">From Idea to Storybook in 3 Simple Steps</h2>
                </div>
                <div class="js-stagger-group grid gap-6 md:grid-cols-3">
                    <div
                        v-for="step in steps"
                        :key="step.num"
                        class="js-stagger-item card-pop relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:border-violet-300 hover:bg-violet-50/40 dark:border-white/[0.07] dark:bg-white/3 dark:hover:border-violet-500/30 dark:hover:bg-violet-500/4"
                    >
                        <div class="mb-4 text-5xl font-black text-violet-500/20 leading-none">{{ step.num }}</div>
                        <h3 class="mb-2 text-base font-bold">{{ step.title }}</h3>
                        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FEATURES ────────────────────────────────────────── -->
        <section class="js-reveal px-5 py-20 md:py-28" id="pricing">
            <div class="mx-auto max-w-5xl">
                <div class="mb-14 text-center">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">What Makes It Powerful</p>
                    <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">More Than Just Story Creation</h2>
                    <p class="mx-auto mt-3 max-w-xl text-sm text-slate-600 dark:text-slate-400">Create, read, listen, and learn in one storytelling platform for children.</p>
                </div>
                <div class="js-stagger-group grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="f in features"
                        :key="f.title"
                        class="js-stagger-item card-pop group rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-1 hover:border-violet-300 hover:bg-violet-50/40 dark:border-white/[0.07] dark:bg-white/3 dark:hover:border-violet-500/30 dark:hover:bg-violet-500/4"
                    >
                        <div
                            class="mb-4 inline-flex size-10 items-center justify-center rounded-xl bg-violet-600/15 text-violet-400 ring-1 ring-violet-500/20"
                            v-html="f.icon"
                        />
                        <h3 class="mb-1.5 text-sm font-bold">{{ f.title }}</h3>
                        <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ f.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="js-reveal px-5 py-20 md:py-24">
            <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/8 dark:bg-white/3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Classroom Use Cases</p>
                    <h3 class="mt-2 text-2xl font-bold">Perfect for Learning Activities</h3>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li>Creative writing assignments</li>
                        <li>Reading comprehension exercises</li>
                        <li>ESL and early literacy support</li>
                        <li>Student author classroom projects</li>
                        <li>Homework storytelling tasks</li>
                    </ul>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/8 dark:bg-white/3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">For Parents</p>
                    <h3 class="mt-2 text-2xl font-bold">Learning That Feels Like Play</h3>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li>Turn imagination into real storybooks</li>
                        <li>Improve reading confidence</li>
                        <li>Replace passive screen time with creativity</li>
                        <li>Create stories together as a family</li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="js-reveal px-5 py-20 md:py-24">
            <div class="mx-auto max-w-5xl rounded-3xl border border-slate-200 bg-white p-8 text-center dark:border-white/8 dark:bg-white/3">
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Live Demo</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight md:text-4xl">See a Story Created in Seconds</h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
                    Preview an interactive flipbook workflow and see how quickly students can go from an idea to a finished story.
                </p>
                <p class="mt-3 text-sm font-semibold text-violet-700 dark:text-violet-300">Story generated in 42 seconds</p>
                <div class="mt-6">
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="register()"
                        class="inline-flex items-center gap-2 rounded-full bg-violet-600 px-7 py-3 text-sm font-bold transition hover:bg-violet-500"
                    >
                        Create Your First Story
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-full bg-violet-600 px-7 py-3 text-sm font-bold transition hover:bg-violet-500"
                    >
                        Create Your First Story
                    </Link>
                </div>
            </div>
        </section>

        <section class="js-reveal px-5 py-20 md:py-24">
            <div class="mx-auto max-w-5xl text-center">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Why It Works</p>
                <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Designed for How Children Learn</h2>
                <div class="js-stagger-group mt-8 grid gap-4 sm:grid-cols-2">
                    <div class="js-stagger-item rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700 dark:border-white/8 dark:bg-white/3 dark:text-slate-300">Visual and audio learning combined</div>
                    <div class="js-stagger-item rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700 dark:border-white/8 dark:bg-white/3 dark:text-slate-300">Active participation, not passive reading</div>
                    <div class="js-stagger-item rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700 dark:border-white/8 dark:bg-white/3 dark:text-slate-300">Instant feedback with quizzes</div>
                    <div class="js-stagger-item rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700 dark:border-white/8 dark:bg-white/3 dark:text-slate-300">Builds creativity and student ownership</div>
                </div>
            </div>
        </section>

        <!-- ── PLANS ───────────────────────────────────────────── -->
        <section class="js-reveal px-5 py-20 md:py-28">
            <div class="mx-auto max-w-6xl">
                <div class="mb-14 text-center">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Plans & Access</p>
                    <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Simple Plans for Families and Schools</h2>
                    <p class="mx-auto mt-3 max-w-xl text-sm text-slate-600 dark:text-slate-400">Start free, then move to Creator or Classroom as your usage grows.</p>
                </div>

                <div v-if="plans.length === 0" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600 dark:border-white/8 dark:bg-white/3 dark:text-slate-400">
                    Plans are being prepared.
                </div>

                <div v-else class="js-stagger-group grid gap-5 md:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.id"
                        class="js-stagger-item relative rounded-2xl border p-6 transition"
                        :class="plan.is_featured
                            ? 'border-violet-400/60 bg-violet-600/10 shadow-lg shadow-violet-900/30'
                            : 'border-slate-200 bg-white hover:border-violet-300 dark:border-white/8 dark:bg-white/3 dark:hover:border-violet-500/30'"
                    >
                        <div
                            v-if="plan.is_featured"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-violet-600 px-3 py-1 text-[11px] font-bold uppercase tracking-wider"
                        >
                            Most Popular
                        </div>

                        <div class="mb-4">
                            <p class="text-xs uppercase tracking-widest text-violet-600 dark:text-violet-300">{{ plan.tier }}</p>
                            <h3 class="mt-1 text-2xl font-bold">{{ plan.tier === 'basic' ? 'Starter' : plan.tier === 'pro' ? 'Creator' : 'Classroom / Pro' }}</h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                {{
                                    plan.tier === 'basic'
                                        ? 'Starter access for parents and teachers exploring the platform.'
                                        : plan.tier === 'pro'
                                            ? 'Creator access for regular storytelling at home or in class.'
                                            : 'Classroom access for higher usage across multiple students.'
                                }}
                            </p>
                        </div>

                        <div class="mb-4">
                            <p class="text-3xl font-extrabold">{{ formatPrice(plan.price_cents, plan.currency) }}</p>
                            <p class="text-xs text-slate-500">Includes story creation capacity. Credits are used in the background.</p>
                        </div>

                        <ul class="mb-6 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            <li v-for="feature in tierSummary(plan.tier)" :key="feature" class="flex items-start gap-2">
                                <span class="mt-0.5 text-violet-400">✓</span>
                                <span>{{ feature }}</span>
                            </li>
                        </ul>

                        <Link
                            v-if="!$page.props.auth.user && canRegister"
                            :href="register()"
                            class="inline-flex w-full items-center justify-center rounded-full bg-violet-600 px-5 py-2.5 text-sm font-semibold transition hover:bg-violet-500"
                        >
                            Get {{ plan.name }}
                        </Link>
                        <Link
                            v-else-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex w-full items-center justify-center rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 dark:border-white/15 dark:text-slate-200 dark:hover:border-white/30"
                        >
                            Open Dashboard
                        </Link>
                        <Link
                            v-else
                            :href="login()"
                            class="inline-flex w-full items-center justify-center rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 dark:border-white/15 dark:text-slate-200 dark:hover:border-white/30"
                        >
                            Login to upgrade
                        </Link>
                    </article>
                </div>
            </div>
        </section>

        <!-- ── FAQ ──────────────────────────────────────────────── -->
        <section class="js-reveal px-5 py-20 md:py-24">
            <div class="mx-auto max-w-4xl">
                <div class="mb-10 text-center">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">FAQ</p>
                    <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Frequently Asked Questions</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
                        Click any question to expand and see the answer.
                    </p>
                </div>

                <div class="js-stagger-group space-y-3">
                    <details
                        v-for="faq in faqs"
                        :key="faq.question"
                        class="js-stagger-item group rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-violet-300 dark:border-white/8 dark:bg-white/3 dark:hover:border-violet-500/40"
                    >
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-sm font-semibold text-slate-800 outline-none marker:content-[''] dark:text-slate-100">
                            <span>{{ faq.question }}</span>
                            <span class="inline-flex size-6 items-center justify-center rounded-full border border-slate-300 text-slate-500 transition group-open:rotate-45 dark:border-white/20 dark:text-slate-300">
                                +
                            </span>
                        </summary>
                        <p class="mt-3 pr-8 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            {{ faq.answer }}
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <section class="js-reveal px-5 pb-20 md:pb-24">
            <div class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-7 dark:border-white/8 dark:bg-white/3">
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">Safety and Trust</p>
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight md:text-3xl">Built with Children in Mind</h2>
                <div class="mt-4 grid gap-3 text-sm text-slate-700 dark:text-slate-300 md:grid-cols-3">
                    <p>Classroom-safe content generation with guided story workflows.</p>
                    <p>No complex setup or technical skills required for teachers or parents.</p>
                    <p>Designed for guided learning environments at school and at home.</p>
                </div>
            </div>
        </section>

        <!-- ── CTA SECTION ─────────────────────────────────────── -->
        <section class="js-reveal px-5 py-20 md:py-28">
            <div class="relative mx-auto max-w-3xl overflow-hidden rounded-3xl border border-violet-300/50 bg-linear-to-br from-violet-100 via-white to-indigo-100 px-8 py-14 text-center shadow-2xl shadow-violet-200/70 dark:border-violet-500/20 dark:from-violet-950/60 dark:via-[#12122a] dark:to-indigo-950/60 dark:shadow-violet-950/60">
                <!-- Glow -->
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div class="absolute inset-0 rounded-3xl bg-linear-to-br from-violet-600/10 via-transparent to-indigo-600/10" />
                </div>
                <div class="relative">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-violet-700 dark:text-violet-400">Start Creating</p>
                    <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Start Creating Your First Storybook Today</h2>
                    <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        No experience needed. Just an idea.
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        <Link
                            v-if="!$page.props.auth.user && canRegister"
                            :href="register()"
                            class="inline-flex items-center gap-2 rounded-full bg-violet-600 px-8 py-3.5 text-sm font-bold shadow-lg shadow-violet-600/30 transition hover:-translate-y-0.5 hover:bg-violet-500 active:scale-95"
                        >
                            Get Started Free →
                        </Link>
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex items-center gap-2 rounded-full bg-violet-600 px-8 py-3.5 text-sm font-bold shadow-lg shadow-violet-600/30 transition hover:-translate-y-0.5 hover:bg-violet-500 active:scale-95"
                        >
                            Go to Dashboard →
                        </Link>
                        <Link
                            v-if="!$page.props.auth.user"
                            :href="login()"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-8 py-3.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-white/15 dark:text-slate-300 dark:hover:border-white/30 dark:hover:text-white"
                        >
                            Already have an account?
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FOOTER ──────────────────────────────────────────── -->
        <footer class="border-t border-slate-200 px-5 py-8 dark:border-white/6">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4">
                <div class="flex items-center">
                    <img src="/images/logo-without-bg.png" alt="DreamForge AI" class="h-14 w-auto object-contain opacity-70" />
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-600">© 2026 DreamForge AI. All rights reserved.</p>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-600">
                    <Link v-if="$page.props.auth.user" :href="dashboard()" class="transition hover:text-slate-800 dark:hover:text-slate-400">Dashboard</Link>
                    <template v-else>
                        <Link :href="login()" class="transition hover:text-slate-800 dark:hover:text-slate-400">Log in</Link>
                        <Link v-if="canRegister" :href="register()" class="transition hover:text-slate-800 dark:hover:text-slate-400">Register</Link>
                    </template>
                    <Link href="/terms" class="transition hover:text-slate-800 dark:hover:text-slate-400">Terms</Link>
                    <Link href="/privacy-policy" class="transition hover:text-slate-800 dark:hover:text-slate-400">Privacy Policy</Link>
                </div>
            </div>
        </footer>
    </div>
</template>


