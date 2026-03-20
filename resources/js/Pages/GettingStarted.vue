<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookCopy, Bot, FileStack, FlaskConical, FolderKanban, LayoutDashboard, Shield, Wrench } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { routeWithQuery } from '@/lib/urlState';

const props = defineProps({
    overview: {
        type: Object,
        required: true,
    },
    journey: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const abilities = computed(() => page.props.auth?.abilities ?? []);
const canManageTasks = computed(() => abilities.value.includes('manage_use_cases'));
const canManagePrompts = computed(() => abilities.value.includes('manage_prompts'));
const canRunExperiments = computed(() => abilities.value.includes('run_experiments'));
const canManageMembers = computed(() => abilities.value.includes('manage_members'));
const canManageConnections = computed(() => abilities.value.includes('manage_connections'));
const canManageWorkspace = computed(() => abilities.value.includes('manage_team'));

const journeyStage = computed(() => props.journey.stage ?? 'empty');
const latestUseCase = computed(() => props.journey.latest_use_case ?? null);
const latestPromptTemplate = computed(() => props.journey.latest_prompt_template ?? null);
const latestPromptVersion = computed(() => props.journey.latest_prompt_version ?? null);
const latestExperiment = computed(() => props.journey.latest_experiment ?? null);
const latestLibraryEntry = computed(() => props.journey.latest_library_entry ?? null);

const tasksListHref = computed(() => route('use-cases.index'));
const createTaskHref = computed(() =>
    canManageTasks.value
        ? routeWithQuery('use-cases.index', {}, { tab: 'create' })
        : route('use-cases.index'),
);
const latestTaskHref = computed(() =>
    latestUseCase.value?.id
        ? routeWithQuery('use-cases.show', latestUseCase.value.id, { tab: 'overview' })
        : route('use-cases.index'),
);
const createPromptHref = computed(() =>
    canManagePrompts.value && latestUseCase.value?.id
        ? routeWithQuery('prompt-templates.create', {}, { use_case_id: latestUseCase.value.id })
        : route('prompt-templates.index'),
);
const latestPromptHref = computed(() => {
    if (latestPromptTemplate.value?.id) {
        return routeWithQuery('prompt-templates.show', latestPromptTemplate.value.id, {
            tab: 'versions',
            prompt_version_id: latestPromptVersion.value?.id ?? '',
        });
    }

    return route('prompt-templates.index');
});
const latestExperimentSetupHref = computed(() =>
    routeWithQuery('playground', {}, {
        step: 'setup',
        mode: 'single',
        use_case_id: latestPromptVersion.value?.use_case_id ?? latestPromptTemplate.value?.use_case_id ?? latestUseCase.value?.id ?? '',
        prompt_template_id: latestPromptVersion.value?.prompt_template_id ?? latestPromptTemplate.value?.id ?? '',
        prompt_version_id: latestPromptVersion.value?.id ?? '',
    }),
);
const latestExperimentHref = computed(() =>
    latestExperiment.value?.id
        ? routeWithQuery('experiments.show', latestExperiment.value.id, { tab: 'results' })
        : route('playground'),
);
const latestLibraryHref = computed(() =>
    latestLibraryEntry.value?.id
        ? route('library.show', latestLibraryEntry.value.id)
        : route('library.index'),
);

const primaryAction = computed(() => {
    if (journeyStage.value === 'empty') {
        return canManageTasks.value
            ? { label: 'Create first task', href: createTaskHref.value }
            : { label: 'Open tasks', href: tasksListHref.value };
    }

    if (journeyStage.value === 'task_defined') {
        return canManagePrompts.value
            ? { label: 'Add first prompt', href: createPromptHref.value }
            : { label: 'Open last task', href: latestTaskHref.value };
    }

    if (journeyStage.value === 'prompting') {
        return canRunExperiments.value
            ? { label: 'Start first test', href: latestExperimentSetupHref.value }
            : { label: 'Open latest prompt', href: latestPromptHref.value };
    }

    if (journeyStage.value === 'testing') {
        return { label: 'View latest result', href: latestExperimentHref.value };
    }

    return { label: 'Open last task', href: latestTaskHref.value };
});

const secondaryActions = computed(() => {
    if (journeyStage.value === 'empty') {
        return [];
    }

    if (journeyStage.value === 'task_defined') {
        return [{ label: 'Open last task', href: latestTaskHref.value }];
    }

    if (journeyStage.value === 'prompting') {
        return [
            { label: 'Open latest prompt', href: latestPromptHref.value },
            { label: 'Open task', href: latestTaskHref.value },
        ];
    }

    if (journeyStage.value === 'testing') {
        return [
            { label: 'Open latest prompt', href: latestPromptHref.value },
            { label: 'Open last task', href: latestTaskHref.value },
        ];
    }

    return [
        { label: 'View latest result', href: latestExperimentHref.value },
        { label: 'Open library', href: latestLibraryHref.value },
    ];
});

const stageCopy = computed(() => {
    const map = {
        empty: {
            eyebrow: 'First workspace step',
            title: canManageTasks.value ? 'Create your first task' : 'Open the task list',
            body: canManageTasks.value
                ? 'Start with the business task. It becomes the anchor for prompts, tests, and approved library entries.'
                : 'This workspace has no tasks yet. Open the task list first, or ask an editor to create the first one.',
        },
        task_defined: {
            eyebrow: 'Activation step',
            title: canManagePrompts.value ? 'Add the first prompt' : 'Review the current task',
            body: canManagePrompts.value
                ? 'The task exists. Next add the first prompt so the team can start testing versions against it.'
                : 'The task is already defined. Open it to review the scope before prompt work starts.',
        },
        prompting: {
            eyebrow: 'Activation step',
            title: canRunExperiments.value ? 'Start the first test' : 'Review the latest prompt',
            body: canRunExperiments.value
                ? 'You already have prompt content. The next meaningful result is a real test run with saved context.'
                : 'A prompt already exists. Open it first so you can review what will be tested next.',
        },
        testing: {
            eyebrow: 'Review step',
            title: 'Review the latest result',
            body: 'Testing has started. Open the latest result, compare what happened, and decide what should be kept or changed.',
        },
        operating: {
            eyebrow: 'Daily work',
            title: 'Continue the main workflow',
            body: 'This workspace is already active. Go back to tasks first, then move to prompts, tests, and library only when needed.',
        },
    };

    return map[journeyStage.value] ?? map.empty;
});

const stageOrder = ['empty', 'task_defined', 'prompting', 'testing', 'operating'];
const stageRank = computed(() => stageOrder.indexOf(journeyStage.value));
const flowSteps = computed(() => [
    {
        id: 'tasks',
        title: '1. Tasks',
        body: 'Define the business problem and collect saved test cases.',
        state: stageRank.value >= 1 ? 'Done' : 'Now',
    },
    {
        id: 'prompts',
        title: '2. Prompts',
        body: 'Write and revise prompt versions for the selected task.',
        state: stageRank.value >= 2 ? 'Done' : stageRank.value === 1 ? 'Now' : 'Later',
    },
    {
        id: 'experiments',
        title: '3. Experiments',
        body: 'Run a prompt, compare versions, and review the result.',
        state: stageRank.value >= 3 ? 'Done' : stageRank.value === 2 ? 'Now' : 'Later',
    },
    {
        id: 'library',
        title: '4. Library',
        body: 'Save approved prompt versions only after they are trusted.',
        state: stageRank.value >= 4 ? 'Done' : stageRank.value === 3 ? 'Now' : 'Later',
    },
]);

const recentWorkLinks = computed(() => {
    const links = [];

    if (latestUseCase.value?.id) {
        links.push({
            title: 'Last task',
            body: latestUseCase.value.name,
            href: latestTaskHref.value,
            icon: FolderKanban,
        });
    }

    if (latestPromptTemplate.value?.id) {
        links.push({
            title: 'Last prompt',
            body: latestPromptTemplate.value.name,
            href: latestPromptHref.value,
            icon: FileStack,
        });
    }

    if (latestExperiment.value?.id) {
        links.push({
            title: 'Last result',
            body: latestExperiment.value.use_case || 'Experiment result',
            href: latestExperimentHref.value,
            icon: FlaskConical,
        });
    }

    if (latestLibraryEntry.value?.id) {
        links.push({
            title: 'Last library entry',
            body: `${latestLibraryEntry.value.prompt_name || 'Approved prompt'} ${latestLibraryEntry.value.version_label || ''}`.trim(),
            href: latestLibraryHref.value,
            icon: BookCopy,
        });
    }

    return links.slice(0, 3);
});

const supportLinks = computed(() => {
    const links = [
        {
            title: 'Dashboard',
            body: 'Use this only when you need the workspace overview and attention queue.',
            href: route('dashboard'),
            icon: LayoutDashboard,
        },
    ];

    if (canManageWorkspace.value) {
        links.push({
            title: 'Workspace setup',
            body: 'Create a new workspace or review the current one. Switching stays in the sidebar.',
            href: routeWithQuery('admin.workspaces', {}, { tab: 'current' }),
            icon: Wrench,
        });
    }

    if (canManageMembers.value) {
        links.push({
            title: 'Users & Access',
            body: 'Add people and update roles when the core workflow is already in place.',
            href: routeWithQuery('admin.users-access', {}, { tab: 'members' }),
            icon: Shield,
        });
    }

    if (canManageConnections.value) {
        links.push({
            title: 'AI Connections',
            body: 'Configure providers only when you need to manage workspace setup.',
            href: routeWithQuery('admin.ai-connections', {}, { tab: 'connections' }),
            icon: Bot,
        });
    }

    return links;
});

const workspaceSnapshot = computed(() => [
    {
        label: 'Tasks',
        value: props.journey.counts?.use_cases ?? props.overview.counts?.use_cases ?? 0,
    },
    {
        label: 'Prompts',
        value: props.journey.counts?.prompt_templates ?? props.overview.counts?.prompt_templates ?? 0,
    },
    {
        label: 'Experiments',
        value: props.journey.counts?.runs ?? props.overview.counts?.runs ?? 0,
    },
    {
        label: 'Library',
        value: props.journey.counts?.library_entries ?? props.overview.counts?.library_entries ?? 0,
    },
]);
</script>

<template>
    <Head title="How to Start" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-black tracking-tight">How to Start</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Use this page to see the next sensible step, not every possible page.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-6">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-[var(--accent)]">{{ stageCopy.eyebrow }}</div>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[var(--ink)]">{{ stageCopy.title }}</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-[var(--muted)]">
                            {{ stageCopy.body }}
                        </p>

                        <div class="mt-5">
                            <Link :href="primaryAction.href" class="btn-primary">{{ primaryAction.label }}</Link>
                        </div>

                        <div v-if="secondaryActions.length" class="mt-4 flex flex-wrap gap-4 text-sm">
                            <Link
                                v-for="action in secondaryActions"
                                :key="action.label"
                                :href="action.href"
                                class="app-inline-link"
                            >
                                {{ action.label }}
                            </Link>
                        </div>
                    </div>

                    <div class="w-full lg:max-w-sm">
                        <div class="summary-strip">
                            <div
                                v-for="item in workspaceSnapshot"
                                :key="item.label"
                                class="summary-item"
                            >
                                <div class="summary-item-label">{{ item.label }}</div>
                                <div class="summary-item-value">{{ item.value }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Main working path"
                    description="Keep the daily flow in this order so the interface stays predictable."
                    help="Tasks stay first, then prompts, then experiments, and only after that the approved library."
                />

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="step in flowSteps" :key="step.id" class="quick-link-card">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-bold text-[var(--ink)]">{{ step.title }}</div>
                            <span class="status-chip">{{ step.state }}</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-[var(--muted)]">{{ step.body }}</div>
                    </div>
                </div>
            </section>

            <section v-if="recentWorkLinks.length" class="panel p-5">
                <PanelHeader
                    title="Continue from recent work"
                    description="These links resume context, but they do not replace the main working path."
                    help="Shows the last task, prompt, result, or library entry so repeat users can continue from something real."
                />

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <Link
                        v-for="item in recentWorkLinks"
                        :key="item.title"
                        :href="item.href"
                        class="quick-link-card block"
                    >
                        <div class="flex items-start gap-3">
                            <component :is="item.icon" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                            <div>
                                <div class="font-bold">{{ item.title }}</div>
                                <div class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ item.body }}</div>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <section v-if="supportLinks.length" class="panel p-5">
                <PanelHeader
                    title="Supporting pages"
                    description="Use these less often. They support the workflow but should not compete with it."
                    help="Administrative and overview pages stay secondary so the daily flow keeps pointing back to tasks, prompts, and experiments."
                />

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Link
                        v-for="item in supportLinks"
                        :key="item.title"
                        :href="item.href"
                        class="quick-link-card block"
                    >
                        <div class="flex items-start gap-3">
                            <component :is="item.icon" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                            <div>
                                <div class="font-bold">{{ item.title }}</div>
                                <div class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ item.body }}</div>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
