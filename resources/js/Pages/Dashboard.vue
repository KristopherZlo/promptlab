<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ChartColumn,
    ClipboardList,
    Medal,
    Shield,
} from 'lucide-vue-next';
import { formatScore } from '@/lib/formatters';

const props = defineProps({
    overview: {
        type: Object,
        required: true,
    },
    models: {
        type: Array,
        required: true,
    },
});

const scoreLabel = (value) => value == null ? 'Not scored' : value.toFixed(1);

const topModel = computed(() => props.overview.top_models[0] ?? null);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">Operational summary for tasks, experiments, approvals, and quality signals.</p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Tasks</div>
                            <div class="summary-item-value">{{ overview.counts.use_cases }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Prompt templates</div>
                            <div class="summary-item-value">{{ overview.counts.prompt_templates }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Runs</div>
                            <div class="summary-item-value">{{ overview.counts.runs }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Approved prompts</div>
                            <div class="summary-item-value">{{ overview.counts.library_entries }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Link :href="route('use-cases.index')" class="btn-secondary">Open tasks</Link>
                        <Link :href="route('playground')" class="btn-primary">Open experiments</Link>
                    </div>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Recent experiments"
                        description="Open a run to continue review."
                        :icon="Activity"
                    />
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Mode</th>
                            <th>Model</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="experiment in overview.recent_experiments" :key="experiment.id">
                            <td>
                                <Link :href="route('experiments.show', experiment.id)" class="font-bold hover:underline">
                                    {{ experiment.use_case || 'Ad hoc' }}
                                </Link>
                            </td>
                            <td class="capitalize">{{ experiment.mode }}</td>
                            <td class="mono text-xs">{{ experiment.model_name }}</td>
                            <td><span class="status-chip">{{ experiment.status }}</span></td>
                        </tr>
                        <tr v-if="overview.recent_experiments.length === 0">
                            <td colspan="4" class="text-[var(--muted)]">No experiments yet.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Signals"
                    description="Use these lists to decide what needs work and what is ready to reuse."
                    :icon="ChartColumn"
                />

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="panel-muted p-4">
                        <div class="flex items-center gap-2 font-semibold">
                            <AlertTriangle class="h-4 w-4 text-[var(--danger)]" />
                            Problem cases
                        </div>
                        <div class="mt-3 space-y-3">
                            <div
                                v-for="item in overview.problem_cases"
                                :key="item.id"
                                class="guide-card"
                            >
                                <div class="font-bold">{{ item.title }}</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">{{ item.use_case }}</div>
                                <div class="mt-2 text-sm">{{ item.failed_count }} failed or invalid runs</div>
                            </div>
                            <div v-if="overview.problem_cases.length === 0" class="text-sm text-[var(--muted)]">
                                No problem cases identified yet.
                            </div>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="flex items-start gap-3">
                            <Medal class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                            <div class="w-full">
                                <div class="font-semibold">Top performing prompts</div>
                                <div class="mt-3 space-y-3">
                                    <div
                                        v-for="prompt in overview.top_performing_prompts"
                                        :key="prompt.id"
                                        class="guide-card"
                                    >
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="font-bold">{{ prompt.name }} <span class="text-[var(--muted)]">{{ prompt.version_label }}</span></div>
                                                <div class="mt-1 text-sm text-[var(--muted)]">{{ prompt.use_case }}</div>
                                            </div>
                                            <div class="text-right text-sm font-bold">{{ scoreLabel(prompt.average_score) }}</div>
                                        </div>
                                    </div>
                                    <div v-if="overview.top_performing_prompts.length === 0" class="text-sm text-[var(--muted)]">
                                        No scored prompt versions yet.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="flex items-start gap-3">
                            <ClipboardList class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                            <div class="w-full">
                                <div class="font-semibold">Most used prompts</div>
                                <div class="mt-3 space-y-3">
                                    <div
                                        v-for="prompt in overview.most_used_prompts"
                                        :key="prompt.id"
                                        class="guide-card"
                                    >
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="font-bold">{{ prompt.name }} <span class="text-[var(--muted)]">{{ prompt.version_label }}</span></div>
                                                <div class="mt-1 text-sm text-[var(--muted)]">{{ prompt.use_case }}</div>
                                            </div>
                                            <div class="text-sm">{{ prompt.runs }} runs</div>
                                        </div>
                                    </div>
                                    <div v-if="overview.most_used_prompts.length === 0" class="text-sm text-[var(--muted)]">
                                        No usage history yet.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-[var(--danger)]" />
                            <div class="w-full">
                                <div class="font-semibold">Failed format outputs</div>
                                <div class="mt-3 space-y-3">
                                    <div
                                        v-for="item in overview.failed_format_outputs"
                                        :key="item.id"
                                        class="guide-card"
                                    >
                                        <div class="font-bold">{{ item.prompt }} <span class="text-[var(--muted)]">{{ item.version_label }}</span></div>
                                        <div class="mt-1 text-sm text-[var(--muted)]">{{ item.use_case }}</div>
                                        <div class="mt-2 text-sm">{{ item.error }}</div>
                                    </div>
                                    <div v-if="overview.failed_format_outputs.length === 0" class="text-sm text-[var(--muted)]">
                                        No recent invalid-format outputs.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="flex items-start gap-3">
                            <Shield class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                            <div class="w-full">
                                <div class="font-semibold">Model coverage</div>
                                <div class="mt-3 space-y-3">
                                    <div v-if="topModel" class="guide-card">
                                        <div class="font-semibold">Top model right now</div>
                                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                            {{ topModel.model_name }} leads with average score {{ formatScore(topModel.average_score) }}.
                                        </div>
                                    </div>

                                    <div
                                        v-for="model in models.slice(0, 4)"
                                        :key="model.value"
                                        class="guide-card"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <div class="font-bold">{{ model.label }}</div>
                                                <div class="mt-1 text-xs text-[var(--muted)] mono">{{ model.value }}</div>
                                            </div>
                                            <span class="status-chip">{{ model.available ? 'Available' : 'Unavailable' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
