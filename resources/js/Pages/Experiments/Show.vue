<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EvaluationPanel from '@/Components/EvaluationPanel.vue';
import HelpHint from '@/Components/HelpHint.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { Activity, BadgeCheck, Bot, ClipboardList, Clock3, Coins, FileCode2, FileText, Gauge, ListChecks, TriangleAlert } from 'lucide-vue-next';
import { formatDateTime, formatScore, safeJsonStringify, truncateText } from '@/lib/formatters';
import { useUrlState } from '@/lib/urlState';

const props = defineProps({
    experiment: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const canManageLibrary = computed(() => (page.props.auth.abilities ?? []).includes('manage_library'));

const experimentState = ref(props.experiment);
const promotionMessages = reactive({});
let pollHandle = null;
const tabItems = [
    { id: 'results', label: 'Results', icon: ListChecks },
    { id: 'summary', label: 'Summary', icon: Activity },
];
const activeTab = useUrlState({
    key: 'tab',
    defaultValue: 'results',
    allowedValues: tabItems.map((item) => item.id),
});
const selectedRunId = useUrlState({
    key: 'run',
    defaultValue: '',
    omitIf: '',
});

const syncSelectedRunId = (value = experimentState.value) => {
    const nextRuns = value?.runs ?? [];

    if (value?.mode !== 'batch' || nextRuns.length === 0) {
        if (selectedRunId.value) {
            selectedRunId.value = '';
        }

        return;
    }

    const hasSelectedRun = nextRuns.some((run) => `${run.id}` === selectedRunId.value);

    if (!hasSelectedRun) {
        selectedRunId.value = `${nextRuns[0].id}`;
    }
};

watch(
    () => props.experiment,
    (value) => {
        experimentState.value = value;
        syncSelectedRunId(value);
    },
    { deep: true },
);

const runs = computed(() => experimentState.value.runs ?? []);
const summary = computed(() => experimentState.value.summary ?? {});
const isRunning = computed(() => ['queued', 'running'].includes(experimentState.value.status));
const compareGridClasses = computed(() =>
    runs.value.length > 2
        ? 'grid gap-4 xl:grid-cols-2 2xl:grid-cols-3'
        : 'grid gap-4 xl:grid-cols-2',
);
const problemRuns = computed(() =>
    runs.value.filter((run) => ['failed', 'invalid_format'].includes(run.status) || run.format_valid === false),
);
const batchActiveRun = computed(() =>
    runs.value.find((run) => `${run.id}` === selectedRunId.value) ?? runs.value[0] ?? null,
);

const loadExperiment = async () => {
    const response = await axios.get(route('api.experiments.show', experimentState.value.id));
    experimentState.value = response.data.data;
    syncSelectedRunId(experimentState.value);
};

const startPolling = () => {
    if (pollHandle) {
        return;
    }

    pollHandle = window.setInterval(() => {
        loadExperiment();
    }, 4000);
};

const stopPolling = () => {
    if (!pollHandle) {
        return;
    }

    window.clearInterval(pollHandle);
    pollHandle = null;
};

watch(isRunning, (running) => {
    if (running) {
        startPolling();
        return;
    }

    stopPolling();
}, { immediate: true });

onMounted(() => {
    if (!window.Echo) {
        return;
    }

    window.Echo.private(`experiments.${experimentState.value.id}`)
        .listen('.experiment.progress', () => {
            loadExperiment();
        });
});

onBeforeUnmount(() => {
    stopPolling();

    if (window.Echo) {
        window.Echo.leave(`private-experiments.${experimentState.value.id}`);
    }
});

watch(
    () => [experimentState.value.mode, runs.value.map((run) => run.id).join(',')],
    () => {
        syncSelectedRunId();
    },
    { immediate: true },
);

const promoteRun = async (run) => {
    if (!canManageLibrary.value) {
        return;
    }

    promotionMessages[run.id] = 'Promoting...';

    try {
        await axios.post(route('api.library-entries.store'), {
            prompt_version_id: run.prompt_version.id,
            recommended_model: experimentState.value.model_name,
            best_for: experimentState.value.use_case?.name || run.prompt_version.use_case || null,
            usage_notes: `Promoted from experiment #${experimentState.value.id}.`,
        });

        promotionMessages[run.id] = 'Promoted to library.';
    } catch (error) {
        promotionMessages[run.id] = error.response?.data?.message || 'Promotion failed.';
    }
};
</script>

<template>
    <Head :title="`Experiment #${experiment.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight">
                        {{ experimentState.use_case?.name || 'Ad hoc experiment' }}
                    </h1>
                    <div class="mt-2 inline-meta">
                        <span class="inline-meta-item">
                            <ListChecks />
                            {{ experimentState.mode }}
                        </span>
                        <span class="inline-meta-item">
                            <Bot />
                            {{ experimentState.model_name }}
                        </span>
                        <span class="inline-meta-item">
                            <Activity />
                            {{ experimentState.status }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="button" class="btn-secondary" @click="loadExperiment">Refresh</button>
                    <Link :href="route('playground')" class="btn-primary">New run</Link>
                </div>
            </div>
        </template>

        <div class="page-frame">
            <aside class="page-frame-rail">
                <button
                    v-for="tab in tabItems"
                    :key="tab.id"
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                    <span>{{ tab.label }}</span>
                </button>
            </aside>

            <div class="page-frame-content">
            <section class="panel p-5">
                <PanelHeader
                    title="Experiment snapshot"
                    description="Current progress, quality, and runtime signals for this experiment."
                    help="Shows the high-level progress and quality metrics for the experiment so reviewers can understand overall status before drilling into individual runs."
                />

                <div class="summary-strip mt-4">
                    <div class="summary-item">
                        <div class="summary-item-label">Progress</div>
                        <div class="summary-item-value">{{ experimentState.completed_runs }}/{{ experimentState.total_runs }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Average score</div>
                        <div class="summary-item-value">{{ formatScore(summary.average_manual_score) }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Format pass rate</div>
                        <div class="summary-item-value">
                            {{ summary.format_pass_rate != null ? `${summary.format_pass_rate}%` : 'N/A' }}
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Average latency</div>
                        <div class="summary-item-value">
                            {{ summary.average_latency_ms != null ? `${summary.average_latency_ms} ms` : 'N/A' }}
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'results' && experimentState.mode !== 'batch'" class="space-y-4">
                <section v-if="experimentState.mode === 'compare'" class="panel p-5">
                    <PanelHeader
                        title="Compare board"
                        description="Review the candidate revisions side by side before deciding which one should move forward."
                        :icon="ClipboardList"
                        help="Compare mode keeps every candidate visible at once so you can inspect outputs, prompts, and evaluations without scrolling through separate pages."
                    />

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="run in runs" :key="`summary-${run.id}`" class="guide-card">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-bold">{{ run.prompt_version?.version_label || `Run #${run.id}` }}</div>
                                    <div class="mt-1 text-sm text-[var(--muted)]">{{ run.prompt_version?.name || 'Unnamed prompt' }}</div>
                                </div>
                                <span class="status-chip">{{ run.status }}</span>
                            </div>

                            <div class="summary-list mt-4">
                                <div class="summary-row">
                                    <span>Model</span>
                                    <span class="mono text-xs">{{ experimentState.model_name }}</span>
                                </div>
                                <div class="summary-row">
                                    <span>Score</span>
                                    <span>{{ formatScore(run.manual_average_score) }}</span>
                                </div>
                                <div class="summary-row">
                                    <span>Format</span>
                                    <span>{{ run.format_valid ? 'Valid' : 'Invalid' }}</span>
                                </div>
                                <div class="summary-row">
                                    <span>Latency</span>
                                    <span>{{ run.latency_ms != null ? `${run.latency_ms} ms` : 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div :class="experimentState.mode === 'compare' ? compareGridClasses : 'space-y-4'">
                    <div v-for="run in runs" :key="run.id" class="panel p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-bold">{{ run.prompt_version?.name }} {{ run.prompt_version?.version_label }}</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">
                                    {{ run.prompt_version?.use_case || experimentState.use_case?.name || 'No task' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <HelpHint
                                    text="This run card contains one prompt version output, its compiled prompt, runtime metrics, and evaluation controls."
                                    :label="`Help for run ${run.id}`"
                                />
                                <span class="status-chip">{{ run.status }}</span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                            <div class="guide-card">
                                <div class="inline-meta-item text-xs text-[var(--muted)]">
                                    <Clock3 />
                                    <span>Latency</span>
                                </div>
                                <div class="mt-1">{{ run.latency_ms != null ? `${run.latency_ms} ms` : 'N/A' }}</div>
                            </div>
                            <div class="guide-card">
                                <div class="inline-meta-item text-xs text-[var(--muted)]">
                                    <Coins />
                                    <span>Tokens</span>
                                </div>
                                <div class="mt-1">{{ run.token_input ?? 'N/A' }} in / {{ run.token_output ?? 'N/A' }} out</div>
                            </div>
                            <div class="guide-card">
                                <div class="inline-meta-item text-xs text-[var(--muted)]">
                                    <BadgeCheck />
                                    <span>Format</span>
                                </div>
                                <div class="mt-1">{{ run.format_valid ? 'Valid' : 'Invalid' }}</div>
                            </div>
                            <div class="guide-card">
                                <div class="inline-meta-item text-xs text-[var(--muted)]">
                                    <Gauge />
                                    <span>Manual average</span>
                                </div>
                                <div class="mt-1">{{ formatScore(run.manual_average_score) }}</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="label-with-icon">
                                <FileText />
                                <span>Input</span>
                            </div>
                            <div class="guide-card mt-2 text-sm leading-6">{{ run.input_text }}</div>
                        </div>

                        <div class="mt-4">
                            <div class="label-with-icon">
                                <Bot />
                                <span>Output</span>
                            </div>
                            <pre class="code-block mt-2">{{ run.output_text || run.error_message || 'No output yet.' }}</pre>
                            <pre v-if="run.output_json" class="code-block mt-3">{{ safeJsonStringify(run.output_json, '{}') }}</pre>
                        </div>

                        <div class="mt-4">
                            <div class="label-with-icon">
                                <FileCode2 />
                                <span>Compiled prompt</span>
                            </div>
                            <pre class="code-block mt-2">{{ run.compiled_prompt || 'Prompt compilation is not available yet.' }}</pre>
                        </div>

                        <div v-if="run.error_message" class="mt-4 flex items-start gap-2 rounded-[8px] border border-[var(--danger)]/20 bg-[rgba(224,30,90,0.08)] px-4 py-3 text-sm text-[var(--danger)]">
                            <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{{ run.error_message }}</span>
                        </div>

                        <div class="mt-4">
                            <EvaluationPanel :run="run" @saved="loadExperiment" />
                        </div>

                        <div v-if="canManageLibrary" class="mt-4 flex items-center justify-between gap-4">
                            <div class="text-sm text-[var(--muted)]">
                                {{ promotionMessages[run.id] || 'Promote this version only if the team would confidently reuse it.' }}
                            </div>
                            <button type="button" class="btn-secondary" @click="promoteRun(run)">
                                Promote to library
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div v-else-if="activeTab === 'results'" class="space-y-6">
                <section v-if="problemRuns.length" class="panel p-5">
                    <PanelHeader
                        title="Problem queue"
                        description="Jump straight into failed or invalid cases first."
                        :icon="TriangleAlert"
                        help="Surfaces the runs that need immediate review so batch experiments do not require scanning the full table for failures."
                    />

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button
                            v-for="run in problemRuns"
                            :key="`problem-${run.id}`"
                            type="button"
                            class="btn-secondary"
                            @click="selectedRunId = `${run.id}`"
                        >
                            {{ run.test_case?.title || `Run #${run.id}` }}
                        </button>
                    </div>
                </section>

                <section class="panel overflow-hidden">
                    <div class="border-b border-[var(--line)] px-5 py-4">
                        <PanelHeader
                            title="Batch runs"
                            description="Open a row to inspect the strongest or weakest outputs first."
                            :icon="ListChecks"
                            help="Lists every batch result so reviewers can pick a saved case and inspect its output in detail."
                        />
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Test case</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Latency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="run in runs"
                                :key="run.id"
                                class="cursor-pointer"
                                :class="{
                                    'bg-[rgba(255,255,255,0.04)]': `${run.id}` === selectedRunId,
                                    'bg-[rgba(224,30,90,0.08)]': ['failed', 'invalid_format'].includes(run.status) || run.format_valid === false,
                                }"
                                @click="selectedRunId = `${run.id}`"
                            >
                                <td>
                                    <div class="font-bold">{{ run.test_case?.title || `Run #${run.id}` }}</div>
                                    <div class="mt-1 text-sm text-[var(--muted)]">{{ truncateText(run.input_text, 120) }}</div>
                                </td>
                                <td><span class="status-chip">{{ run.status }}</span></td>
                                <td>{{ formatScore(run.manual_average_score) }}</td>
                                <td>{{ run.latency_ms != null ? `${run.latency_ms} ms` : 'N/A' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section v-if="batchActiveRun" class="panel p-5">
                    <div class="flex items-start justify-between gap-4">
                        <PanelHeader
                            :title="batchActiveRun.test_case?.title || `Run #${batchActiveRun.id}`"
                            :description="`${batchActiveRun.prompt_version?.name} ${batchActiveRun.prompt_version?.version_label}`"
                            :icon="ClipboardList"
                            help="Shows the currently selected batch result, including the input, output, compiled prompt, and evaluation tools for that case."
                        />
                        <span class="status-chip">{{ batchActiveRun.status }}</span>
                    </div>

                    <div
                        v-if="batchActiveRun.error_message"
                        class="mt-4 flex items-start gap-2 rounded-[8px] border border-[var(--danger)]/20 bg-[rgba(224,30,90,0.08)] px-4 py-3 text-sm text-[var(--danger)]"
                    >
                        <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ batchActiveRun.error_message }}</span>
                    </div>

                    <div class="mt-4">
                        <div class="label-with-icon">
                            <FileText />
                            <span>Input</span>
                        </div>
                        <div class="guide-card mt-2 text-sm leading-6">{{ batchActiveRun.input_text }}</div>
                    </div>

                    <div class="mt-4">
                        <div class="label-with-icon">
                            <Bot />
                            <span>Output</span>
                        </div>
                        <pre class="code-block mt-2">{{ batchActiveRun.output_text || batchActiveRun.error_message || 'No output yet.' }}</pre>
                        <pre v-if="batchActiveRun.output_json" class="code-block mt-3">{{ safeJsonStringify(batchActiveRun.output_json, '{}') }}</pre>
                    </div>

                    <div class="mt-4">
                        <div class="label-with-icon">
                            <FileCode2 />
                            <span>Compiled prompt</span>
                        </div>
                        <pre class="code-block mt-2">{{ batchActiveRun.compiled_prompt || 'Prompt compilation is not available yet.' }}</pre>
                    </div>

                    <div class="mt-4">
                        <EvaluationPanel :run="batchActiveRun" @saved="loadExperiment" />
                    </div>
                </section>
            </div>

            <section v-if="activeTab === 'summary'" class="panel p-5">
                <PanelHeader
                    title="Experiment context and summary"
                    description="Use this section to understand what settings were used and how the run behaved overall."
                    :icon="Activity"
                    help="Explains the experiment configuration and aggregates the most important result signals after execution."
                />

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <ClipboardList />
                            <span>Run metadata</span>
                        </div>
                        <div class="summary-list mt-4">
                            <div class="summary-row">
                                <span>Mode</span>
                                <span class="capitalize">{{ experimentState.mode }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Provider</span>
                                <span>{{ experimentState.provider }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Model</span>
                                <span class="mono text-xs">{{ experimentState.model_name }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Temperature</span>
                                <span>{{ experimentState.temperature }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Max tokens</span>
                                <span>{{ experimentState.max_tokens }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Created</span>
                                <span>{{ formatDateTime(experimentState.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <Gauge />
                            <span>Experiment summary</span>
                        </div>
                        <div class="summary-list mt-4">
                            <div class="summary-row">
                                <span>Evaluated runs</span>
                                <span>{{ summary.evaluated_runs ?? 0 }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Failed runs</span>
                                <span>{{ experimentState.failed_runs }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Total runs</span>
                                <span>{{ experimentState.total_runs }}</span>
                            </div>
                        </div>

                        <div v-if="Object.keys(summary.most_common_errors ?? {}).length" class="mt-4">
                            <div class="label-with-icon">
                                <TriangleAlert />
                                <span>Most common errors</span>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(count, message) in summary.most_common_errors"
                                    :key="message"
                                    class="guide-card"
                                >
                                    <div class="font-bold">{{ message }}</div>
                                    <div class="mt-2 text-sm text-[var(--muted)]">{{ count }} runs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
