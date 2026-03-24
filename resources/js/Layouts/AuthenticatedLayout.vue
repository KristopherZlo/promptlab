<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BadgeInfo,
    BookCopy,
    Bot,
    Building2,
    ChevronDown,
    ChevronRight,
    Compass,
    FileStack,
    FlaskConical,
    FolderKanban,
    History,
    LayoutDashboard,
    LogOut,
    Menu,
    Settings,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-vue-next';
import { formatPlatformRoleLabel, formatRoleLabel } from '@/lib/forms';
import Dropdown from '@/Components/Dropdown.vue';

const page = usePage();
const mobileOpen = ref(false);
const switchingTeam = ref(false);

const iconMap = {
    dashboard: LayoutDashboard,
    tasks: FolderKanban,
    prompts: FileStack,
    experiments: FlaskConical,
    library: BookCopy,
    acknowledgements: BadgeInfo,
    'users-access': Users,
    workspaces: Building2,
    'ai-connections': Bot,
    'audit-log': History,
    profile: UserRound,
    'team-workspace': ShieldCheck,
    guide: Compass,
};

const dataTourMap = {
    tasks: 'nav-use-cases',
    prompts: 'nav-prompt-templates',
    experiments: 'nav-playground',
    library: 'nav-library',
    'team-workspace': 'nav-team-access',
};

const user = computed(() => page.props.auth?.user);
const currentTeam = computed(() => page.props.auth?.current_team);
const navigationSections = computed(() => page.props.navigation?.sections ?? []);
const homeHref = computed(() => page.props.navigation?.home_url ?? route('use-cases.index'));

const workspaceTools = computed(() => [
    {
        id: 'guide',
        label: 'How to Start',
        route: 'getting-started',
        current: ['getting-started'],
    },
    {
        id: 'team-workspace',
        label: 'Workspace Setup',
        route: 'admin.workspaces',
        current: ['admin.workspaces', 'team-workspace.index'],
    },
    {
        id: 'acknowledgements',
        label: 'Acknowledgements',
        route: 'acknowledgements.index',
        current: ['acknowledgements.index'],
    },
]);

const teamOptions = computed(() => {
    const teams = page.props.auth?.teams ?? [];

    if (teams.length) {
        return teams;
    }

    return currentTeam.value ? [currentTeam.value] : [];
});

const isActive = (item) => item.current.some((pattern) => route().current(pattern));
const iconFor = (item) => iconMap[item.id] ?? LayoutDashboard;
const dataTourFor = (item) => dataTourMap[item.id] ?? null;

const currentSection = computed(() => navigationSections.value.find((section) => section.items.some(isActive)) ?? null);
const currentItem = computed(() =>
    currentSection.value?.items.find(isActive)
    ?? workspaceTools.value.find(isActive)
    ?? navigationSections.value.flatMap((section) => section.items).find(isActive)
    ?? null,
);
const hrefForItem = (item) => {
    if (! item?.route) {
        return null;
    }

    return route(item.route);
};
const hrefForSection = (section) => {
    if (! section) {
        return null;
    }

    if (section.id === 'workspace') {
        return route('dashboard');
    }

    return hrefForItem(section.items?.[0] ?? null);
};
const breadcrumbs = computed(() => {
    const items = [{ label: currentTeam.value?.name || 'Workspace', href: homeHref.value }];

    if (currentSection.value?.label && currentSection.value.label !== currentItem.value?.label) {
        const sectionHref = hrefForSection(currentSection.value);

        items.push({
            label: currentSection.value.label,
            href: sectionHref && sectionHref !== hrefForItem(currentItem.value) ? sectionHref : null,
        });
    }

    if (currentItem.value?.label) {
        items.push({ label: currentItem.value.label, href: null });
    }

    return items;
});

const userRoleLabel = computed(() => {
    if (currentTeam.value?.team_role) {
        return formatRoleLabel(currentTeam.value.team_role);
    }

    return formatPlatformRoleLabel(user.value?.platform_role);
});

const userInitials = computed(() => {
    const firstName = `${user.value?.first_name ?? ''}`.trim();
    const lastName = `${user.value?.last_name ?? ''}`.trim();

    if (firstName || lastName) {
        return `${firstName.charAt(0)}${lastName.charAt(0)}`.trim().toUpperCase();
    }

    return `${user.value?.name ?? ''}`
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
});

const teamInitials = computed(() =>
    `${currentTeam.value?.name ?? 'Workspace'}`
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase(),
);

const switchTeam = async (event) => {
    const teamId = Number(event.target.value);

    if (!teamId || teamId === currentTeam.value?.id) {
        return;
    }

    switchingTeam.value = true;

    try {
        const response = await axios.post(route('api.teams.switch'), { team_id: teamId });
        router.visit(response.data?.redirect_url ?? homeHref.value);
    } finally {
        switchingTeam.value = false;
    }
};

const closeMobileMenu = () => {
    mobileOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-[var(--canvas)] text-[var(--ink)]">
        <div class="app-shell">
            <div v-if="mobileOpen" class="app-overlay" @click="closeMobileMenu"></div>

            <aside class="app-sidebar" :class="{ 'app-sidebar-open': mobileOpen }">
                <div class="app-sidebar-head">
                    <Link :href="homeHref" class="app-brand" @click="closeMobileMenu">
                        <span class="app-brand-mark">{{ teamInitials }}</span>
                        <div class="min-w-0">
                            <div class="app-brand-title">PromptFactory</div>
                            <div class="app-brand-meta">{{ currentTeam?.name || 'Personal workspace' }}</div>
                        </div>
                    </Link>
                </div>

                <div class="app-sidebar-block">
                    <label class="field-label mb-2">Workspace</label>
                    <div class="shell-select-wrap">
                        <select
                            data-tour="team-switcher"
                            class="field-select shell-select shell-select-sidebar"
                            :value="currentTeam?.id || ''"
                            :disabled="switchingTeam"
                            @change="switchTeam"
                        >
                            <option v-for="team in teamOptions" :key="team.id" :value="team.id">
                                {{ team.name }}
                            </option>
                        </select>
                        <ChevronDown class="shell-select-icon" />
                    </div>
                </div>

                <nav class="app-sidebar-nav">
                    <div v-for="section in navigationSections" :key="section.id" class="app-nav-group">
                        <div class="app-nav-group-label">{{ section.label }}</div>
                        <div class="space-y-1">
                            <Link
                                v-for="item in section.items"
                                :key="item.id"
                                :href="route(item.route)"
                                class="app-nav-item"
                                :class="{ 'app-nav-item-active': isActive(item) }"
                                :data-tour="dataTourFor(item)"
                                @click="closeMobileMenu"
                            >
                                <component :is="iconFor(item)" class="h-4 w-4 shrink-0" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </div>
                    </div>

                    <div class="app-nav-group">
                        <div class="app-nav-group-label">Tools</div>
                        <div class="space-y-1">
                            <Link
                                v-for="item in workspaceTools"
                                :key="item.id"
                                :href="route(item.route)"
                                class="app-nav-item"
                                :class="{ 'app-nav-item-active': isActive(item) }"
                                :data-tour="dataTourFor(item)"
                                @click="closeMobileMenu"
                            >
                                <component :is="iconFor(item)" class="h-4 w-4 shrink-0" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </div>
                    </div>
                </nav>

            </aside>

            <div class="app-main">
                <header class="app-topbar">
                    <div class="app-topbar-row">
                        <div class="app-topbar-path">
                            <button type="button" class="app-mobile-button" @click="mobileOpen = !mobileOpen">
                                <Menu class="h-4 w-4" />
                                <span>Menu</span>
                            </button>
                            <nav class="app-breadcrumb">
                                <template v-for="(item, index) in breadcrumbs" :key="`${item.label}-${index}`">
                                    <ChevronRight v-if="index > 0" class="h-3.5 w-3.5" />
                                    <Link
                                        v-if="item.href && index < breadcrumbs.length - 1"
                                        :href="item.href"
                                        class="app-breadcrumb-link"
                                    >
                                        {{ item.label }}
                                    </Link>
                                    <span v-else :class="{ 'app-breadcrumb-current': index === breadcrumbs.length - 1 }">
                                        {{ item.label }}
                                    </span>
                                </template>
                            </nav>
                        </div>

                        <div class="app-topbar-actions">
                            <Dropdown align="right" width="56" content-classes="profile-menu">
                                <template #trigger>
                                    <button type="button" class="app-user-trigger" aria-label="Open user menu">
                                        <span class="app-avatar">{{ userInitials }}</span>
                                        <div class="app-user-copy">
                                            <div class="app-user-name">{{ user?.name }}</div>
                                            <div class="app-user-role">{{ userRoleLabel }}</div>
                                        </div>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="profile-menu-header">
                                        <div class="app-avatar app-avatar-large">{{ userInitials }}</div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-[var(--ink)]">{{ user?.name }}</div>
                                            <div class="mt-1 text-sm text-[var(--muted)]">{{ userRoleLabel }}</div>
                                        </div>
                                    </div>
                                    <div class="profile-menu-actions">
                                        <Link :href="route('profile.edit')" class="profile-menu-link">
                                            <Settings class="h-4 w-4" />
                                            <span>Profile</span>
                                        </Link>
                                        <Link :href="route('logout')" method="post" as="button" class="btn-danger w-full">
                                            <LogOut class="h-4 w-4" />
                                            <span>Log out</span>
                                        </Link>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </header>

                <main class="app-page">
                    <section class="app-page-card">
                        <div class="app-page-card-head">
                            <div class="app-page-header">
                                <slot name="header" />
                            </div>
                        </div>

                        <div class="app-page-card-body">
                            <slot />
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </div>
</template>
