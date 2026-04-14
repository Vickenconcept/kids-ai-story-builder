<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const toast = useToast();

const toastClasses = (type: 'success' | 'error' | 'info') => {
    if (type === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-100';
    }

    if (type === 'error') {
        return 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/70 dark:bg-red-950/60 dark:text-red-100';
    }

    return 'border-border bg-background text-foreground';
};
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-4 z-100 flex w-[min(26rem,calc(100vw-2rem))] flex-col gap-2">
        <div
            v-for="item in toast.toasts"
            :key="item.id"
            class="pointer-events-auto rounded-lg border px-3 py-2.5 text-sm shadow-lg transition"
            :class="toastClasses(item.type)"
            role="status"
            aria-live="polite"
        >
            <div class="flex items-start justify-between gap-3">
                <p class="leading-5">{{ item.message }}</p>
                <button
                    type="button"
                    class="mt-0.5 text-xs opacity-70 transition hover:opacity-100"
                    @click="toast.removeToast(item.id)"
                >
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</template>
