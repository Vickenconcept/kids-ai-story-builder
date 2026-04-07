<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'DreamForge AI';
</script>

<template>
    <Head title="Thank You" />

    <section class="min-h-screen bg-linear-to-br from-violet-950 via-indigo-950 to-purple-950 px-4 py-12 text-white md:px-8">
        <div class="mx-auto flex max-w-3xl flex-col items-center text-center">
            <div class="mb-8 flex items-center gap-3">
                <img src="/images/logo-without-bg.png" alt="DreamForge AI" class="h-14 w-auto object-contain" />
                <span class="text-xl font-bold tracking-wide">{{ appName }}</span>
            </div>

            <div class="w-full rounded-2xl border border-violet-400/30 bg-white/8 p-8 shadow-2xl shadow-violet-950/70 backdrop-blur-sm md:p-12">
                <p class="text-xs font-semibold uppercase tracking-widest text-fuchsia-300">Purchase Confirmed</p>
                <h1 class="mt-3 text-3xl font-extrabold md:text-5xl">Thank You for Your Purchase!</h1>
                <p class="mx-auto mt-4 max-w-xl text-sm text-violet-200 md:text-base">
                    Nice one. Your access is ready and you can start creating storybooks right away.
                </p>

                <div class="mt-8 grid gap-3 text-left text-sm text-violet-100 md:grid-cols-3">
                    <div class="rounded-xl border border-violet-400/20 bg-violet-500/10 p-4">
                        <p class="font-semibold">1) Log in</p>
                        <p class="mt-1 text-violet-200">Use your account email and password.</p>
                    </div>
                    <div class="rounded-xl border border-violet-400/20 bg-violet-500/10 p-4">
                        <p class="font-semibold">2) Create a story</p>
                        <p class="mt-1 text-violet-200">Pick a prompt and generate your first book.</p>
                    </div>
                    <div class="rounded-xl border border-violet-400/20 bg-violet-500/10 p-4">
                        <p class="font-semibold">3) Publish or share</p>
                        <p class="mt-1 text-violet-200">Export or share your flipbook in one click.</p>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <Link href="/login" class="rounded-full bg-linear-to-r from-fuchsia-500 to-violet-600 px-7 py-3 text-sm font-bold text-white transition hover:from-fuchsia-400 hover:to-violet-500">
                        Go to Login
                    </Link>
                    <Link href="/dashboard" class="rounded-full border border-violet-300/40 bg-violet-500/10 px-7 py-3 text-sm font-semibold text-violet-100 transition hover:bg-violet-500/20">
                        Open Dashboard
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
