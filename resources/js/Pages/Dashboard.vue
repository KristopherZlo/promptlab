<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { FlaskConical, Gauge } from 'lucide-vue-next';
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

const activeTab = ref('overview');
const topModel = computed(() => props.overview.top_models[0] ?? null);

const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'prompts', label: 'Prompts' },
    { id: 'models', label: 'Models' },
    { id: 'attention', label: 'Attention' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Dashboard</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Separate views for workspace totals, prompt quality, available models, and items that need review.
                </p>
            </div>
        </template>

        <div class="page-stack">
            <div class="page-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    <span>{{ tab.label }}</span>
                </button>
            </div>

            <div class="page-frame-content">
                <section v-if="activeTab === 'overview'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Workspace overview</h2>
                            <p class="text-sm text-[var(--muted)]">Current volume across tasks, templates, experiments, and approved prompts.</p>
                        </div>
                        <div class="console-page-actions">
                            <Link :href="route('use-cases.index')" class="btn-secondary">Tasks</Link>
                            <Link :href="route('prompt-templates.index')" class="btn-secondary">Templates</Link>
                            <Link :href="route('playground')" class="btn-primary">New run</Link>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-6">
                        <div class="summary-strip">
                            <div class="summary-item">
                                <div class="summary-item-label">Experiment runs</div>
                                <div class="summary-item-value">{{ overview.counts.runs }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Tasks</div>
                                <div class="summary-item-value">{{ overview.counts.use_cases }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Templates</div>
                                <div class="summary-item-value">{{ overview.counts.prompt_templates }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Approved prompts</div>
                                <div class="summary-item-value">{{ overview.counts.library_entries }}</div>
                            </div>
                        </div>

                        <div class="surface-muted">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="section-title">Recent experiments</h3>
                                <Link :href="route('playground')" class="app-inline-link">Open experiments</Link>
                            </div>

                            <table class="data-table mt-4">
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
                                            <Link :href="route('experiments.show', experiment.id)" class="font-semibold hover:underline">
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
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'prompts'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Prompt performance</h2>
                            <p class="text-sm text-[var(--muted)]">Keep ranking and reuse decisions in a dedicated view instead of mixing them into the main dashboard.</p>
                        </div>
                    </div>

                    <div class="surface-block-body grid gap-4 xl:grid-cols-2">
                        <div class="surface-muted">
                            <div class="flex items-center gap-2 text-sm font-semibold text-[var(--ink)]">
                                <Gauge class="h-4 w-4" />
                                <span>Top performing prompts</span>
                            </div>
                            <div class="record-list mt-4">
                                <div v-for="prompt in overview.top_performing_prompts.slice(0, 6)" :key="prompt.id" class="record-list-item">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="font-semibold text-[var(--ink)]">
                                                {{ prompt.name }} <span class="text-[var(--muted)]">{{ prompt.version_label }}</span>
                                            </div>
                                            <div class="mt-1 text-sm text-[var(--muted)]">{{ prompt.use_case }}</div>
                                        </div>
                                        <div class="text-sm font-semibold">{{ prompt.average_score?.toFixed(1) ?? 'Not scored' }}</div>
                                    </div>
                                </div>
                                <div v-if="overview.top_performing_prompts.length === 0" class="record-list-item text-sm text-[var(--muted)]">
                                    No scored prompt versions yet.
                                </div>
                            </div>
                        </div>

                        <div class="surface-muted">
                            <div class="flex items-center gap-2 text-sm font-semibold text-[var(--ink)]">
                                <FlaskConical class="h-4 w-4" />
                                <span>Most used prompts</span>
                            </div>
                            <div class="record-list mt-4">
                                <div v-for="prompt in overview.most_used_prompts.slice(0, 6)" :key="prompt.id" class="record-list-item">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="font-semibold text-[var(--ink)]">
                                                {{ prompt.name }} <span class="text-[var(--muted)]">{{ prompt.version_label }}</span>
                                            </div>
                                            <div class="mt-1 text-sm text-[var(--muted)]">{{ prompt.use_case }}</div>
                                        </div>
                                        <div class="text-sm">{{ prompt.runs }} runs</div>
                                    </div>
                                </div>
                                <div v-if="overview.most_used_prompts.length === 0" class="record-list-item text-sm text-[var(--muted)]">
                                    No usage history yet.
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'models'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Models</h2>
                            <p class="text-sm text-[var(--muted)]">Availability and scoring stay in their own screen so operational status does not compete with content review.</p>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-4">
                        <div class="surface-muted">
                            <div class="text-sm font-semibold text-[var(--ink)]">Leading model</div>
                            <div class="mt-3 text-lg font-semibold text-[var(--ink)]">
                                {{ topModel?.model_name || 'No scored model yet' }}
                            </div>
                            <div class="mt-2 text-sm text-[var(--muted)]">
                                <template v-if="topModel">
                                    Average score {{ formatScore(topModel.average_score) }}.
                                </template>
                                <template v-else>
                                    Model rankings will appear after scored runs exist.
                                </template>
                            </div>
                        </div>

                        <div class="surface-muted">
                            <div class="text-sm font-semibold text-[var(--ink)]">Available models</div>
                            <div class="record-list mt-4">
                                <div v-for="model in models" :key="model.value" class="record-list-item">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="font-semibold text-[var(--ink)]">{{ model.label }}</div>
                                            <div class="mt-1 mono text-xs text-[var(--muted)]">{{ model.value }}</div>
                                        </div>
                                        <span class="status-chip">{{ model.available ? 'Available' : 'Unavailable' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Attention queue</h2>
                            <p class="text-sm text-[var(--muted)]">Keep failed cases and invalid outputs in a dedicated review queue.</p>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-4">
                        <div class="surface-muted">
                            <div class="text-sm font-semibold text-[var(--ink)]">Problem cases</div>
                            <div class="record-list mt-4">
                                <div v-for="item in overview.problem_cases" :key="item.id" class="record-list-item">
                                    <div class="font-semibold text-[var(--ink)]">{{ item.title }}</div>
                                    <div class="mt-1 text-sm text-[var(--muted)]">{{ item.use_case }}</div>
                                    <div class="mt-2 text-sm">{{ item.failed_count }} failed or invalid runs</div>
                                </div>
                                <div v-if="overview.problem_cases.length === 0" class="record-list-item text-sm text-[var(--muted)]">
                                    No active problem cases.
                                </div>
                            </div>
                        </div>

                        <div class="surface-muted">
                            <div class="text-sm font-semibold text-[var(--ink)]">Invalid format outputs</div>
                            <div class="record-list mt-4">
                                <div v-for="item in overview.failed_format_outputs" :key="`failed-${item.id}`" class="record-list-item">
                                    <div class="font-semibold text-[var(--ink)]">
                                        {{ item.prompt }} <span class="text-[var(--muted)]">{{ item.version_label }}</span>
                                    </div>
                                    <div class="mt-1 text-sm text-[var(--muted)]">{{ item.use_case }}</div>
                                    <div class="mt-2 text-sm">{{ item.error }}</div>
                                </div>
                                <div v-if="overview.failed_format_outputs.length === 0" class="record-list-item text-sm text-[var(--muted)]">
                                    No invalid format outputs right now.
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
