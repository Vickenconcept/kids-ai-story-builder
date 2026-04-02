<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookMarked, BookOpen, Coins, FolderGit2, LayoutGrid, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Stories',
            href: '/stories',
            icon: BookMarked,
        },
        {
            title: 'Buy Credits',
            href: '/credits',
            icon: Coins,
        },
    ];

    if (page.props.auth?.canManageCreditPacks) {
        items.push({
            title: 'Credit Packs',
            href: '/admin/credit-packs',
            icon: ShieldCheck,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <div class="px-2 pb-2">
                <Button class="w-full" type="button" variant="outline" @click="creditsModal.open()">
                    <Coins class="mr-2 size-4" />
                    Instant Top-Up
                </Button>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
