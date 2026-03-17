<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { BookCopy, ChevronRight, Compass, FileStack, FlaskConical, FolderKanban, LayoutDashboard, UserRound, Users } from 'lucide-vue-next';

const page = usePage();
const mobileOpen = ref(false);
const switchingTeam = ref(false);

const navigationGroups = [
    {
        label: 'Orientation',
        items: [
            { label: 'Start Here', route: 'getting-started', current: ['getting-started'], icon: Compass },
            { label: 'Dashboard', route: 'dashboard', current: ['dashboard'], icon: LayoutDashboard },
        ],
    },
    {
        label: 'Build',
        items: [
            { label: 'Tasks', route: 'use-cases.index', current: ['use-cases.*'], icon: FolderKanban, tour: 'nav-use-cases' },
            { label: 'Prompt Templates', route: 'prompt-templates.index', current: ['prompt-templates.*', 'prompt-versions.*'], icon: FileStack, tour: 'nav-prompt-templates' },
        ],
    },
    {
        label: 'Run and Review',
        items: [
            { label: 'Playground', route: 'playground', current: ['playground', 'experiments.show'], icon: FlaskConical, tour: 'nav-playground' },
            { label: 'Approved Library', route: 'library.index', current: ['library.*'], icon: BookCopy, tour: 'nav-library' },
        ],
    },
    {
        label: 'Workspace',
        items: [
            { label: 'Team & Access', route: 'team-workspace.index', current: ['team-workspace.*'], icon: Users, tour: 'nav-team-access' },
        ],
    },
];

const user = computed(() => page.props.auth.user);
const currentTeam = computed(() => page.props.auth.current_team);
const teams = computed(() => page.props.auth.teams ?? []);
const teamOptions = computed(() => {
    if (teams.value.length) {
        return teams.value;
    }

    return currentTeam.value ? [currentTeam.value] : [];
});
const flash = computed(() => page.props.flash?.success);
const isActive = (item) => item.current.some((pattern) => route().current(pattern));
const breadcrumbItems = computed(() => {
    const component = page.component;
    const props = page.props;
    const items = [
        { label: 'PromptLab', href: route('dashboard') },
    ];

    if (component === 'Dashboard') {
        items.push({ label: 'Dashboard' });
        return items;
    }

    if (component === 'GettingStarted') {
        items.push({ label: 'Start Here' });
        return items;
    }

    if (component === 'UseCases/Index') {
        items.push({ label: 'Tasks' });
        return items;
    }

    if (component === 'UseCases/Show') {
        items.push({ label: 'Tasks', href: route('use-cases.index') });
        items.push({ label: props.useCase?.name || 'Task' });
        return items;
    }

    if (component === 'PromptTemplates/Index') {
        items.push({ label: 'Prompt Templates' });
        return items;
    }

    if (component === 'PromptTemplates/Edit') {
        items.push({ label: 'Prompt Templates', href: route('prompt-templates.index') });
        items.push({ label: props.promptTemplate?.name || 'New Template' });
        return items;
    }

    if (component === 'Playground/Index') {
        items.push({ label: 'Playground' });
        return items;
    }

    if (component === 'Experiments/Show') {
        items.push({ label: 'Playground', href: route('playground') });
        items.push({ label: props.experiment?.use_case?.name || `Experiment #${props.experiment?.id ?? ''}`.trim() });
        return items;
    }

    if (component === 'Library/Index') {
        items.push({ label: 'Approved Library' });
        return items;
    }

    if (component === 'TeamWorkspace/Index') {
        items.push({ label: 'Team & Access' });
        return items;
    }

    if (component === 'Profile/Edit') {
        items.push({ label: 'Profile' });
        return items;
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
        <div class="flex min-h-screen">
            <aside class="hidden h-screen w-[260px] shrink-0 border-r border-[var(--sidebar-line)] bg-[var(--sidebar)] text-white lg:sticky lg:top-0 lg:flex lg:flex-col lg:overflow-hidden">
                <div class="border-b border-[var(--sidebar-line)] px-5 py-5">
                    <Link :href="route('dashboard')" class="block">
                        <div class="text-xl font-black tracking-tight">PromptLab</div>
                        <div class="mt-1 text-sm text-white/70">Team prompt experimentation workspace</div>
                    </Link>
                </div>

                <nav class="scrollbar-hidden min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                    <div>
                        <div v-for="group in navigationGroups" :key="group.label">
                            <div class="sidebar-group-label">{{ group.label }}</div>
                            <div class="space-y-1">
                                <Link
                                    v-for="item in group.items"
                                    :key="item.route"
                                    :href="route(item.route)"
                                    :data-tour="item.tour"
                                    class="flex items-center gap-3 rounded-[8px] px-3 py-2.5 text-sm font-bold transition"
                                    :class="isActive(item)
                                        ? 'bg-white text-[var(--sidebar)]'
                                        : 'text-white/76 hover:bg-[var(--sidebar-hover)] hover:text-white'"
                                >
                                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                    <span>{{ item.label }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="border-t border-[var(--sidebar-line)] px-5 py-4">
                    <div class="mb-3">
                        <select
                            class="field-select sidebar-select !py-2"
                            :value="currentTeam?.id || ''"
                            :disabled="switchingTeam || teamOptions.length <= 1"
                            aria-label="Switch active team"
                            data-tour="team-switcher"
                            @change="switchTeam"
                        >
                            <option v-if="!teamOptions.length" value="">
                                No team selected
                            </option>
                            <option v-for="team in teamOptions" :key="team.id" :value="team.id">
                                {{ team.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 text-sm font-bold">
                        <UserRound class="h-4 w-4 shrink-0" />
                        <span>{{ user?.name }}</span>
                    </div>
                    <div class="mt-1 text-sm text-white/68">{{ user?.email }}</div>
                    <div class="mt-4 flex gap-4 text-sm">
                        <Link :href="route('profile.edit')" class="text-white/80 hover:text-white">Profile</Link>
                        <Link :href="route('logout')" method="post" as="button" class="text-white/80 hover:text-white">Log out</Link>
                    </div>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <header class="border-b border-[var(--line)] bg-white">
                    <div class="flex items-center gap-4 px-5 py-4 lg:px-8">
                        <button type="button" class="btn-secondary lg:hidden" @click="mobileOpen = !mobileOpen">
                            Menu
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

                        <div class="hidden self-end gap-3 lg:flex">
                            <Link :href="route('playground')" class="btn-primary">Run experiment</Link>
                        </div>
                    </div>

                    <div v-if="mobileOpen" class="border-t border-[var(--line)] px-5 py-3 lg:hidden">
                        <div v-for="group in navigationGroups" :key="group.label" class="mt-3 first:mt-0">
                            <div class="mb-1 text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted)]">
                                {{ group.label }}
                            </div>
                            <div class="space-y-1">
                                <Link
                                    v-for="item in group.items"
                                    :key="item.route"
                                    :href="route(item.route)"
                                    :data-tour="item.tour"
                                    class="flex items-center gap-3 rounded-[8px] px-3 py-2 text-sm font-bold"
                                    :class="isActive(item)
                                        ? 'bg-[rgba(97,31,105,0.1)] text-[var(--accent)]'
                                        : 'text-[var(--muted)] hover:bg-[var(--panel-muted)] hover:text-[var(--ink)]'"
                                >
                                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                    <span>{{ item.label }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="px-5 py-6 lg:px-8">
                    <div v-if="flash" class="panel mb-6 px-4 py-3 text-sm">{{ flash }}</div>

                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
