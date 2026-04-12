<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookMarked, Coins, Crown, Film, LayoutGrid, Mail, Monitor, Moon, Settings, ShieldCheck, Sparkles, Sun, UserPlus, Users, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { useAppearance } from '@/composables/useAppearance';
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
import { index as videoLibraryIndex } from '@/routes/video-library';
import type { NavItem } from '@/types';

const page = usePage<any>();
const creditsModal = useCreditsModal();
const { appearance, updateAppearance } = useAppearance();

const storyCredits = computed(() => page.props.auth?.user?.story_credits ?? 0);
const featureTier = computed(() => page.props.auth?.user?.feature_tier ?? 'basic');
const appearanceOptions = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
] as const;

const cycleAppearance = () => {
    const next = appearance.value === 'light'
        ? 'dark'
        : appearance.value === 'dark'
            ? 'system'
            : 'light';

    updateAppearance(next);
};

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Stories', href: '/stories', icon: BookMarked },
    ];

    if (featureTier.value === 'pro' || featureTier.value === 'elite') {
        items.push({ title: 'Video library', href: videoLibraryIndex().url, icon: Film });
    }

    items.push(
        { title: 'Plans', href: '/plans', icon: Crown },
        { title: 'Credits', href: '/credits', icon: Coins },
    );

    if (page.props.auth?.canUseReseller) {
        items.push({ title: 'Reseller', href: '/reseller', icon: UserPlus });
    }

    items.push({ title: 'Settings', href: '/settings', icon: Settings });

    if (page.props.auth?.canManageUsers) {
        items.push({ title: 'Users', href: '/admin/users', icon: Users });
        items.push({ title: 'Marketing email', href: '/admin/marketing-mail', icon: Mail });
    }

    if (page.props.auth?.canManageCreditPacks) {
        items.push({ title: 'Credit Packs', href: '/admin/credit-packs', icon: ShieldCheck });
    }

    if (page.props.auth?.canManagePlans) {
        items.push({ title: 'Plans Admin', href: '/admin/plans', icon: ShieldCheck });
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

            <!-- Appearance quick switch -->
            <div class="px-3 pb-2 group-data-[collapsible=icon]:hidden">
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-2 dark:border-sidebar-border">
                    <p class="px-1 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Appearance</p>
                    <div class="grid grid-cols-3 gap-1">
                        <button
                            v-for="item in appearanceOptions"
                            :key="item.value"
                            type="button"
                            class="inline-flex items-center justify-center gap-1 rounded-md px-2 py-1.5 text-[11px] font-medium transition"
                            :class="appearance === item.value
                                ? 'bg-violet-600 text-white'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="updateAppearance(item.value)"
                        >
                            <component :is="item.icon" class="size-3.5" />
                            <span>{{ item.label }}</span>
                        </button>
                    </div>
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

            <!-- Collapsed icon-only appearance toggle -->
            <div class="hidden px-2 pb-2 group-data-[collapsible=icon]:block">
                <button
                    type="button"
                    class="flex w-full items-center justify-center rounded-lg border border-sidebar-border/70 bg-card p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground dark:border-sidebar-border"
                    title="Switch appearance"
                    @click="cycleAppearance()"
                >
                    <Sun v-if="appearance === 'light'" class="size-4" />
                    <Moon v-else-if="appearance === 'dark'" class="size-4" />
                    <Monitor v-else class="size-4" />
                </button>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
