<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
};

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="text-violet-600 underline decoration-violet-300 underline-offset-4 transition-colors duration-300 ease-out hover:text-violet-500 hover:decoration-current! dark:text-violet-400 dark:decoration-violet-500"
    >
        <slot />
    </Link>
</template>
