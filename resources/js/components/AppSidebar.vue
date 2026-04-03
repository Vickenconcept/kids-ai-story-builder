<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookMarked, Coins, LayoutGrid, Settings, ShieldCheck, Sparkles, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { useCreditsModal } from '@/composables/useCreditsModal';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage<any>();
const creditsModal = useCreditsModal();

const storyCredits = computed(() => page.props.auth?.user?.story_credits ?? 0);
const featureTier = computed(() => page.props.auth?.user?.feature_tier ?? 'basic');

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Stories', href: '/stories', icon: BookMarked },
        { title: 'Credits', href: '/credits', icon: Coins },
        { title: 'Settings', href: '/settings', icon: Settings },
    ];

    if (page.props.auth?.canManageCreditPacks) {
        items.push({ title: 'Credit Packs', href: '/admin/credit-packs', icon: ShieldCheck });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <Link :href="dashboard()" class="flex items-center justify-center px-2 py-3 group-data-[collapsible=icon]:py-2">
                <AppLogo />
            </Link>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <!-- Credits widget -->
            <div class="px-3 pb-1 group-data-[collapsible=icon]:hidden">
                <div class="rounded-xl border border-violet-200/70 bg-linear-to-br from-violet-50 to-indigo-50 p-3 dark:border-violet-800/40 dark:from-violet-950/40 dark:to-indigo-950/40">
                    <!-- Balance row -->
                    <div class="flex items-center justify-between gap-2 mb-2.5">
                        <div class="flex items-center gap-1.5">
                            <Zap class="size-3.5 text-amber-500" />
                            <span class="text-xs font-semibold text-violet-900 dark:text-violet-200">Credits</span>
                        </div>
                        <span class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ storyCredits }}</span>
                    </div>

                    <!-- Tier badge -->
                    <div class="mb-2.5 flex items-center gap-1.5">
                        <Sparkles class="size-3 text-violet-500" />
                        <span class="text-xs capitalize text-violet-700 dark:text-violet-400 font-medium">{{ featureTier }} plan</span>
                    </div>

                    <!-- Top-up button -->
                    <button
                        type="button"
                        class="w-full rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-violet-700 active:scale-95"
                        @click="creditsModal.open()"
                    >
                        + Top Up Credits
                    </button>
                </div>
            </div>

            <!-- Collapsed icon-only top-up button -->
            <div class="hidden px-2 pb-2 group-data-[collapsible=icon]:block">
                <button
                    type="button"
                    class="flex w-full items-center justify-center rounded-lg bg-violet-600 p-2 text-white transition hover:bg-violet-700"
                    title="Top Up Credits"
                    @click="creditsModal.open()"
                >
                    <Zap class="size-4" />
                </button>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
