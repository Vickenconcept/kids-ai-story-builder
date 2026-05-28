<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tutorial', href: '/tutorial' },
];
</script>

<template>
    <Head title="Tutorial" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-muted/30 p-4 md:p-6 dark:bg-muted/10">
            <div class="mx-auto w-full max-w-5xl space-y-5">
                <div class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                    <h1 class="text-2xl font-bold tracking-tight">DreamForge Tutorial</h1>
                    <p class="text-muted-foreground mt-2 text-sm">
                        Watch this walkthrough to learn how to use the app from story creation to publishing.
                    </p>
                </div>

                <div class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                    <div class="aspect-video w-full">
                        <iframe
                            class="h-full w-full"
                            src="https://www.youtube.com/embed/10RpMoI-Zps"
                            title="DreamForge tutorial video"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
