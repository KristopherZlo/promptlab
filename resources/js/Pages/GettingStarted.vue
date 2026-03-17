<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InteractiveOnboarding from '@/Components/InteractiveOnboarding.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { BookCopy, FileStack, FlaskConical, FolderKanban, Shield } from 'lucide-vue-next';

const props = defineProps({
    overview: {
        type: Object,
        required: true,
    },
});

const onboardingOpen = ref(false);

const tourSteps = [
    {
        title: 'Start from the task',
        body: 'Tasks define the business problem, expected output, and saved examples. This is the first stop for most teams.',
        selectors: ['[data-tour="nav-use-cases"]', '[data-tour="start-use-cases"]', '[data-tour="path-use-cases"]'],
    },
    {
        title: 'Edit prompt versions here',
        body: 'Prompt Templates is where wording, variables, and versions are managed before anything gets tested.',
        selectors: ['[data-tour="nav-prompt-templates"]', '[data-tour="path-prompt-templates"]'],
    },
    {
        title: 'Run and compare in Experiments',
        body: 'Experiments is the execution area for one run, compare mode, and experiment review.',
        selectors: ['[data-tour="nav-playground"]', '[data-tour="path-playground"]'],
    },
    {
        title: 'Reuse only approved prompts',
        body: 'Approved Library is the safe handoff point for prompts the team already trusts.',
        selectors: ['[data-tour="nav-library"]', '[data-tour="path-library"]'],
    },
    {
        title: 'The active team changes the workspace',
        body: 'Switch teams here when you need a different set of prompts, experiments, permissions, and model connections.',
        selectors: ['[data-tour="team-switcher"]', '[data-tour="path-team-access"]', '[data-tour="nav-team-access"]'],
    },
];

const primaryPaths = [
    {
        title: 'Start with a task',
        body: 'Begin from the business task and saved examples. This is the best first step for almost everyone.',
        route: 'use-cases.index',
        action: 'Open tasks',
        icon: FolderKanban,
        featured: true,
        tour: 'path-use-cases',
    },
    {
        title: 'Work on prompt versions',
        body: 'Open Prompt Templates when you need to create, edit, or compare prompt versions.',
        route: 'prompt-templates.index',
        action: 'Open prompt templates',
        icon: FileStack,
        tour: 'path-prompt-templates',
    },
    {
        title: 'Run an experiment',
        body: 'Use Experiments to test one version, compare several, or review recent runs.',
        route: 'playground',
        action: 'Open experiments',
        icon: FlaskConical,
        tour: 'path-playground',
    },
];

const supportLinks = [
    {
        title: 'Approved Library',
        body: 'Use this when you need prompts that are already team-ready.',
        route: 'library.index',
        icon: BookCopy,
        tour: 'path-library',
    },
    {
        title: 'Users & Access',
        body: 'Roles, workspace administration, and AI connections live in the Administration area.',
        route: 'admin.users-access',
        icon: Shield,
        tour: 'path-team-access',
    },
];
</script>

<template>
    <Head title="Start Here" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Start Here</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Start from the task, then move to prompts, then run experiments.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-6">
                <PanelHeader
                    title="Recommended order"
                    description="Short guidance for the normal path through the product."
                    help="Explains the shortest working path for most teams: start from tasks, move to prompt templates, then execute in experiments."
                />

                <div class="mt-5 flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <ol class="mt-4 grid gap-3 lg:grid-cols-3">
                            <li class="guide-card">
                                <div class="flex items-start gap-3">
                                    <FolderKanban class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                                    <div>
                                        <div class="font-bold">1. Open Tasks</div>
                                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                            Start from the business task and saved examples.
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="guide-card">
                                <div class="flex items-start gap-3">
                                    <FileStack class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                                    <div>
                                        <div class="font-bold">2. Open Prompt Templates</div>
                                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                            Edit the wording, variables, and versions for that task.
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="guide-card">
                                <div class="flex items-start gap-3">
                                    <FlaskConical class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                                    <div>
                                        <div class="font-bold">3. Open Experiments</div>
                                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                            Run the prompt on real input, compare outputs, and review the result.
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <div class="flex flex-wrap gap-3 self-start">
                        <Link :href="route('use-cases.index')" class="btn-primary" data-tour="start-use-cases">Open tasks</Link>
                        <Link :href="route('prompt-templates.index')" class="btn-secondary">Open prompt templates</Link>
                        <button type="button" class="btn-ghost" @click="onboardingOpen = true">Show quick tour</button>
                    </div>
                </div>
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Main areas"
                    description="These are the only pages most teams need day to day."
                    help="Highlights the primary day-to-day pages used for operational work."
                />

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <Link
                        v-for="item in primaryPaths"
                        :key="item.title"
                        :href="route(item.route)"
                        :data-tour="item.tour"
                        class="quick-link-card block"
                    >
                        <component :is="item.icon" class="h-4 w-4 text-[var(--accent)]" />
                        <div class="mt-3 font-bold">{{ item.title }}</div>
                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ item.body }}</div>
                    </Link>
                </div>
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Other pages"
                    description="Open these when you need approved prompts or workspace setup."
                    help="Covers the secondary pages used for approved prompt reuse and administrative configuration."
                />

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <Link
                        v-for="item in supportLinks"
                        :key="item.title"
                        :href="route(item.route)"
                        :data-tour="item.tour"
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

        <InteractiveOnboarding v-model="onboardingOpen" :steps="tourSteps" />
    </AuthenticatedLayout>
</template>
