<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BookCopy,
    Bot,
    Building2,
    ChevronRight,
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
    'users-access': Users,
    workspaces: Building2,
    'ai-connections': Bot,
    'audit-log': History,
    profile: UserRound,
};

const user = computed(() => page.props.auth?.user);
const currentTeam = computed(() => page.props.auth?.current_team);
const navigationSections = computed(() => page.props.navigation?.sections ?? []);
const teamOptions = computed(() => {
    const teams = page.props.auth?.teams ?? [];

    if (teams.length) {
        return teams;
    }

    return currentTeam.value ? [currentTeam.value] : [];
});

const isActive = (item) => item.current.some((pattern) => route().current(pattern));
const iconFor = (item) => iconMap[item.id] ?? LayoutDashboard;
const closeMobileMenu = () => {
    mobileOpen.value = false;
};
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

const breadcrumbItems = computed(() => {
    const component = page.component;
    const props = page.props;
    const items = [
        { label: 'PromptLab', href: route('dashboard') },
    ];

    if (component === 'Dashboard') {
        return [...items, { label: 'Dashboard' }];
    }

    if (component === 'UseCases/Index') {
        return [...items, { label: 'Tasks' }];
    }

    if (component === 'UseCases/Show') {
        return [...items, { label: 'Tasks', href: route('use-cases.index') }, { label: props.useCase?.name || 'Task' }];
    }

    if (component === 'PromptTemplates/Index') {
        return [...items, { label: 'Prompt Templates' }];
    }

    if (component === 'PromptTemplates/Edit') {
        return [...items, { label: 'Prompt Templates', href: route('prompt-templates.index') }, { label: props.promptTemplate?.name || 'Template' }];
    }

    if (component === 'Playground/Index') {
        return [...items, { label: 'Experiments' }];
    }

    if (component === 'Experiments/Show') {
        return [...items, { label: 'Experiments', href: route('playground') }, { label: props.experiment?.use_case?.name || `Experiment #${props.experiment?.id ?? ''}`.trim() }];
    }

    if (component === 'Library/Index') {
        return [...items, { label: 'Approved Library' }];
    }

    if (component === 'Admin/UsersAccess') {
        return [...items, { label: 'Administration' }, { label: 'Users & Access' }];
    }

    if (component === 'Admin/Workspaces') {
        return [...items, { label: 'Administration' }, { label: 'Workspaces' }];
    }

    if (component === 'Admin/AiConnections') {
        return [...items, { label: 'Administration' }, { label: 'AI Connections' }];
    }

    if (component === 'Admin/AuditLog') {
        return [...items, { label: 'Administration' }, { label: 'Audit Log' }];
    }

    if (component === 'Profile/Edit') {
        return [...items, { label: 'Profile' }];
    }

    if (component === 'GettingStarted') {
        return [...items, { label: 'Help' }];
    }

    return items;
});

const switchTeam = async (event) => {
    const teamId = Number(event.target.value);

    if (!teamId || teamId === currentTeam.value?.id) {
        return;
    }

    switchingTeam.value = true;

    try {
        await axios.post(route('api.teams.switch'), { team_id: teamId });
        router.visit(route('dashboard'));
    } finally {
        switchingTeam.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-[var(--canvas)] text-[var(--ink)]">
        <div class="shell">
            <div v-if="mobileOpen" class="shell-overlay lg:hidden" @click="closeMobileMenu"></div>

            <aside class="shell-sidebar" :class="{ 'shell-sidebar-open': mobileOpen }">
                <div class="shell-brand">
                    <Link :href="route('dashboard')" class="block" @click="closeMobileMenu">
                        <div class="shell-brand-title">PromptLab</div>
                        <div class="shell-brand-subtitle">Internal AI operations workspace</div>
                    </Link>
                </div>

                <nav class="shell-nav">
                    <div v-for="section in navigationSections" :key="section.id" class="shell-nav-section">
                        <div class="shell-nav-label">{{ section.label }}</div>
                        <div class="space-y-1">
                            <Link
                                v-for="item in section.items"
                                :key="item.id"
                                :href="route(item.route)"
                                class="shell-nav-link"
                                :class="{ 'shell-nav-link-active': isActive(item) }"
                                @click="closeMobileMenu"
                            >
                                <component :is="iconFor(item)" class="h-4 w-4 shrink-0" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </div>
                    </div>
                </nav>

                <div class="shell-sidebar-footer">
                    <div class="shell-profile-row">
                        <Dropdown align="left" position="up" width="56" content-classes="profile-menu">
                            <template #trigger>
                                <button type="button" class="shell-avatar-button" aria-label="Open profile menu">
                                    <span class="shell-avatar-circle">{{ userInitials }}</span>
                                </button>
                            </template>

                            <template #content>
                                <div class="profile-menu-header">
                                    <div class="shell-avatar-circle shell-avatar-circle-menu">{{ userInitials }}</div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-[var(--ink)]">{{ user?.name }}</div>
                                        <div class="mt-1 text-sm text-[var(--muted)]">{{ userRoleLabel }}</div>
                                    </div>
                                </div>
                                <div class="profile-menu-actions">
                                    <Link :href="route('profile.edit')" class="profile-menu-link">
                                        <Settings class="h-4 w-4" />
                                        <span>Profile settings</span>
                                    </Link>
                                    <Link :href="route('logout')" method="post" as="button" class="btn-danger w-full">
                                        <LogOut class="h-4 w-4" />
                                        <span>Log out</span>
                                    </Link>
                                </div>
                            </template>
                        </Dropdown>

                        <div class="min-w-0 flex-1">
                            <div class="shell-profile-name">{{ user?.name }}</div>
                            <div class="shell-profile-role">{{ userRoleLabel }}</div>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="shell-main">
                <header class="shell-header">
                    <div class="shell-header-row">
                        <button type="button" class="shell-menu-button lg:hidden" @click="mobileOpen = !mobileOpen">
                            <Menu class="h-4 w-4" />
                            <span>Menu</span>
                        </button>

                        <div class="min-w-0 flex-1">
                            <nav v-if="breadcrumbItems.length" class="breadcrumbs">
                                <template v-for="(item, index) in breadcrumbItems" :key="`${item.label}-${index}`">
                                    <ChevronRight v-if="index > 0" class="breadcrumb-separator" />
                                    <Link
                                        v-if="item.href && index < breadcrumbItems.length - 1"
                                        :href="item.href"
                                        class="breadcrumb-link"
                                    >
                                        {{ item.label }}
                                    </Link>
                                    <span v-else class="breadcrumb-current">{{ item.label }}</span>
                                </template>
                            </nav>
                            <slot name="header" />
                        </div>

                        <div class="shell-header-actions">
                            <div class="shell-team-switch">
                                <label class="shell-meta-label">Workspace</label>
                                <select
                                    class="field-select shell-select"
                                    :value="currentTeam?.id || ''"
                                    :disabled="switchingTeam || teamOptions.length <= 1"
                                    @change="switchTeam"
                                >
                                    <option v-for="team in teamOptions" :key="team.id" :value="team.id">
                                        {{ team.name }}
                                    </option>
                                </select>
                            </div>
                            <Link :href="route('playground')" class="btn-primary">New run</Link>
                        </div>
                    </div>

                    <div class="shell-context-row">
                        <div class="inline-meta text-xs">
                            <span class="inline-meta-item">
                                <ShieldCheck />
                                Platform: {{ formatPlatformRoleLabel(user?.platform_role) }}
                            </span>
                            <span v-if="currentTeam?.team_role" class="inline-meta-item">
                                <ShieldCheck />
                                Workspace: {{ formatRoleLabel(currentTeam.team_role) }}
                            </span>
                            <span v-if="currentTeam?.slug" class="inline-meta-item mono">
                                {{ currentTeam.slug }}
                            </span>
                        </div>
                    </div>
                </header>

                <main class="shell-content">
                    <div v-if="page.props.flash?.success" class="notice-banner mb-6">{{ page.props.flash.success }}</div>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
