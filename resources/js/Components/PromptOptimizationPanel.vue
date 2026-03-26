<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    Bot,
    CheckCircle2,
    Clock3,
    FlaskConical,
    LoaderCircle,
    RefreshCw,
    Target,
    TriangleAlert,
    WandSparkles,
} from 'lucide-vue-next';
import PanelHeader from '@/Components/PanelHeader.vue';
import { extractServerMessage } from '@/lib/forms';
import { formatDateTime, formatScore } from '@/lib/formatters';
import { pushToast } from '@/lib/toasts';

const props = defineProps({
    promptTemplate: {
        type: Object,
        required: true,
    },
    versions: {
        type: Array,
        default: () => [],
    },
    models: {
        type: Array,
        default: () => [],
    },
    optimizationContext: {
        type: Object,
        default: null,
    },
    suggestedSourceVersionId: {
        type: Number,
        default: null,
    },
});

const budgetOptions = [12, 18, 30, 48];
const isStarting = ref(false);
const selectedRunId = ref(null);
let pollTimer = null;

const availableModels = computed(() =>
    props.models.filter((model) => model.available || model.value.startsWith('mock:')),
);
const defaultSourceVersionId = computed(() =>
    props.suggestedSourceVersionId
    || props.optimizationContext?.default_source_prompt_version_id
    || props.versions.at(-1)?.id
    || null,
);
const defaultModelName = computed(() =>
    props.versions.find((version) => version.id === defaultSourceVersionId.value)?.preferred_model
    || props.promptTemplate.preferred_model
    || availableModels.value[0]?.value
    || '',
);
const form = ref({
    source_prompt_version_id: defaultSourceVersionId.value,
    model_name: defaultModelName.value,
    budget_metric_calls: budgetOptions[1],
});

watch(defaultSourceVersionId, (value) => {
    if (! form.value.source_prompt_version_id) {
        form.value.source_prompt_version_id = value;
    }
}, { immediate: true });

watch(defaultModelName, (value) => {
    if (! form.value.model_name) {
        form.value.model_name = value;
    }
}, { immediate: true });

const runs = computed(() => props.optimizationContext?.runs ?? []);
const sourceVersions = computed(() =>
    [...props.versions].slice().reverse(),
);
const versionById = computed(() =>
    new Map(props.versions.map((version) => [version.id, version])),
);
const selectedRun = computed(() =>
    runs.value.find((run) => run.id === selectedRunId.value) ?? runs.value[0] ?? null,
);
const selectedSourceVersion = computed(() =>
    selectedRun.value?.source_version?.id
        ? versionById.value.get(selectedRun.value.source_version.id) ?? null
        : null,
);
const comparisonFields = computed(() => {
    const sourceVersion = selectedSourceVersion.value;
    const bestCandidate = selectedRun.value?.best_candidate ?? {};

    return [
        {
            key: 'system_prompt',
            label: 'System prompt',
            source: `${sourceVersion?.system_prompt ?? ''}`.trim(),
            optimized: `${bestCandidate.system_prompt ?? ''}`.trim(),
        },
        {
            key: 'user_prompt_template',
            label: 'Prompt text',
            source: `${sourceVersion?.user_prompt_template ?? ''}`.trim(),
            optimized: `${bestCandidate.user_prompt_template ?? ''}`.trim(),
        },
    ].filter((field) => field.source !== '' || field.optimized !== '');
});
const hasEligibleCases = computed(() => (props.optimizationContext?.eligible_test_case_count ?? 0) > 0);
const hasActiveRun = computed(() =>
    runs.value.some((run) => ['queued', 'running'].includes(run.status)),
);
const derivedVersionHref = computed(() => {
    if (! selectedRun.value?.derived_version?.id) {
        return '';
    }

    return `${route('prompt-templates.show', props.promptTemplate.id)}?tab=versions&prompt_version_id=${selectedRun.value.derived_version.id}`;
});
const selectedRunStatusLabel = computed(() => {
    const status = selectedRun.value?.status ?? '';

    return status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
});
const startButtonDisabled = computed(() =>
    isStarting.value
    || ! hasEligibleCases.value
    || ! form.value.source_prompt_version_id
    || ! form.value.model_name,
);

watch(runs, (items) => {
    if (! items.length) {
        selectedRunId.value = null;
        return;
    }

    if (! items.some((run) => run.id === selectedRunId.value)) {
        selectedRunId.value = items[0].id;
    }
}, { immediate: true, deep: true });

watch(hasActiveRun, (active) => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }

    if (! active) {
        return;
    }

    pollTimer = window.setInterval(() => {
        router.reload({
            only: ['promptTemplate', 'optimizationContext'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 6000);
}, { immediate: true });

onBeforeUnmount(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
});

const runStatusClass = (status) => ({
    'prompt-optimize-status-success': status === 'completed',
    'prompt-optimize-status-live': status === 'running',
    'prompt-optimize-status-live-muted': status === 'queued',
    'prompt-optimize-status-danger': status === 'failed',
});

const startOptimization = async () => {
    if (startButtonDisabled.value) {
        return;
    }

    isStarting.value = true;

    try {
        await axios.post(route('api.prompt-optimizations.store', props.promptTemplate.id), {
            source_prompt_version_id: form.value.source_prompt_version_id,
            model_name: form.value.model_name,
            budget_metric_calls: form.value.budget_metric_calls,
        });

        pushToast({
            message: 'GEPA optimization started.',
            tone: 'success',
        });

        router.reload({
            only: ['promptTemplate', 'optimizationContext'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch (error) {
        pushToast({
            message: extractServerMessage(error, 'GEPA optimization could not be started.'),
            tone: 'error',
        });
    } finally {
        isStarting.value = false;
    }
};

const refreshRuns = () => {
    router.reload({
        only: ['promptTemplate', 'optimizationContext'],
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <section class="panel p-5">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <PanelHeader
                    title="Prompt improvement"
                    description="Try better prompt wording against saved scenarios and keep the best draft."
                    :icon="Target"
                    help="This uses saved prompt versions plus active scenarios with expected output or expected JSON. GEPA proposes better versions, runs them through the existing workspace model connections, and writes the best candidate back as a new prompt draft."
                />

                <button
                    type="button"
                    class="btn-primary"
                    :disabled="startButtonDisabled"
                    @click="startOptimization"
                >
                    <LoaderCircle v-if="isStarting" class="h-4 w-4 animate-spin" />
                    <WandSparkles v-else class="h-4 w-4" />
                    <span>{{ isStarting ? 'Starting...' : 'Start improvement run' }}</span>
                </button>
            </div>

            <div class="prompt-optimize-layout mt-5">
                <div class="prompt-optimize-config">
                    <div class="prompt-optimize-field">
                        <label class="field-label">Source version</label>
                        <select v-model="form.source_prompt_version_id" class="field-select">
                            <option
                                v-for="version in sourceVersions"
                                :key="version.id"
                                :value="version.id"
                            >
                                {{ version.version_label }}{{ version.change_summary ? ` - ${version.change_summary}` : '' }}
                            </option>
                        </select>
                    </div>

                    <div class="prompt-optimize-field">
                        <label class="field-label">Model</label>
                        <select v-model="form.model_name" class="field-select">
                            <option v-for="model in availableModels" :key="model.value" :value="model.value">
                                {{ model.label }}
                            </option>
                        </select>
                    </div>

                    <div class="prompt-optimize-field">
                        <label class="field-label">Budget</label>
                        <select v-model="form.budget_metric_calls" class="field-select">
                            <option v-for="budget in budgetOptions" :key="budget" :value="budget">
                                {{ budget }} metric calls
                            </option>
                        </select>
                    </div>
                </div>

                <div class="prompt-optimize-dataset">
                    <div class="prompt-optimize-stats">
                        <div class="prompt-optimize-stat">
                            <div class="prompt-optimize-stat-icon">
                                <FlaskConical class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="prompt-optimize-stat-value">{{ optimizationContext?.eligible_test_case_count ?? 0 }}</div>
                                <div class="prompt-optimize-stat-label">Eligible scenarios</div>
                            </div>
                        </div>

                        <div class="prompt-optimize-stat">
                            <div class="prompt-optimize-stat-icon">
                                <Bot class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="prompt-optimize-stat-value">{{ optimizationContext?.train_case_count ?? 0 }}</div>
                                <div class="prompt-optimize-stat-label">Training split</div>
                            </div>
                        </div>

                        <div class="prompt-optimize-stat">
                            <div class="prompt-optimize-stat-icon">
                                <CheckCircle2 class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="prompt-optimize-stat-value">{{ optimizationContext?.validation_case_count ?? 0 }}</div>
                                <div class="prompt-optimize-stat-label">Validation split</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="optimizationContext?.eligible_test_cases?.length" class="prompt-optimize-case-list">
                        <div
                            v-for="testCase in optimizationContext.eligible_test_cases"
                            :key="testCase.id"
                            class="prompt-optimize-case"
                        >
                            <span class="truncate">{{ testCase.title }}</span>
                            <span class="text-xs text-[var(--muted)]">
                                {{ testCase.has_expected_json ? 'JSON' : 'Text' }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="prompt-optimize-empty-note">
                        Add active scenarios with expected output or expected JSON to make improvement runnable.
                    </div>

                    <div class="prompt-optimize-footnote">
                        Improvement runs use saved versions only. Save the current draft first if you want those edits included.
                    </div>
                </div>
            </div>
        </section>

        <section class="panel p-5">
            <div class="flex items-center justify-between gap-4">
                <PanelHeader
                    title="Improvement runs"
                    description="Recent improvement runs, current status, and the best draft returned by each run."
                    :icon="Clock3"
                    help="Queued and running jobs are polled automatically. Completed runs keep their best prompt candidate and, when the candidate changed, produce a new saved draft in prompt history."
                />

                <button type="button" class="btn-secondary" @click="refreshRuns">
                    <RefreshCw class="h-4 w-4" />
                    <span>Refresh</span>
                </button>
            </div>

            <div v-if="runs.length" class="prompt-optimize-results mt-5">
                <aside class="prompt-optimize-run-list">
                    <button
                        v-for="run in runs"
                        :key="run.id"
                        type="button"
                        class="prompt-optimize-run"
                        :class="{ 'prompt-optimize-run-active': run.id === selectedRun?.id }"
                        @click="selectedRunId = run.id"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="prompt-optimize-run-title">
                                    {{ run.source_version?.version_label ?? 'Saved version' }}
                                </div>
                                <div class="prompt-optimize-run-meta">
                                    {{ formatDateTime(run.created_at) }}
                                </div>
                            </div>

                            <span class="prompt-optimize-status" :class="runStatusClass(run.status)">
                                {{ run.status }}
                            </span>
                        </div>

                        <div class="prompt-optimize-run-grid">
                            <div>
                                <div class="prompt-optimize-run-value">{{ run.budget_metric_calls }}</div>
                                <div class="prompt-optimize-run-label">Budget</div>
                            </div>
                            <div>
                                <div class="prompt-optimize-run-value">{{ formatScore(run.best_score) }}</div>
                                <div class="prompt-optimize-run-label">Best score</div>
                            </div>
                            <div>
                                <div class="prompt-optimize-run-value">{{ run.candidate_count ?? 0 }}</div>
                                <div class="prompt-optimize-run-label">Candidates</div>
                            </div>
                        </div>

                        <div class="prompt-optimize-run-model">
                            {{ run.requested_model_name }}
                        </div>
                    </button>
                </aside>

                <div v-if="selectedRun" class="prompt-optimize-detail">
                    <div class="prompt-optimize-detail-header">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-base font-semibold text-[var(--ink)]">
                                    Run #{{ selectedRun.id }}
                                </h3>
                                <span class="prompt-optimize-status" :class="runStatusClass(selectedRun.status)">
                                    {{ selectedRunStatusLabel }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                Started from {{ selectedRun.source_version?.version_label ?? 'a saved version' }} on {{ formatDateTime(selectedRun.created_at) }} using {{ selectedRun.requested_model_name }}.
                            </p>
                        </div>

                        <div v-if="derivedVersionHref" class="shrink-0">
                            <Link :href="derivedVersionHref" class="btn-secondary">
                                Open derived draft
                            </Link>
                        </div>
                    </div>

                    <div class="prompt-optimize-stats mt-5">
                        <div class="prompt-optimize-stat">
                            <div class="prompt-optimize-stat-icon">
                                <CheckCircle2 class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="prompt-optimize-stat-value">{{ formatScore(selectedRun.best_score) }}</div>
                                <div class="prompt-optimize-stat-label">Best validation score</div>
                            </div>
                        </div>

                        <div class="prompt-optimize-stat">
                            <div class="prompt-optimize-stat-icon">
                                <FlaskConical class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="prompt-optimize-stat-value">{{ selectedRun.total_metric_calls ?? 0 }}</div>
                                <div class="prompt-optimize-stat-label">Metric calls</div>
                            </div>
                        </div>

                        <div class="prompt-optimize-stat">
                            <div class="prompt-optimize-stat-icon">
                                <Bot class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="prompt-optimize-stat-value">{{ selectedRun.candidate_count ?? 0 }}</div>
                                <div class="prompt-optimize-stat-label">Candidates explored</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedRun.error_message" class="prompt-optimize-warning mt-5">
                        <TriangleAlert class="h-4 w-4 shrink-0" />
                        <span>{{ selectedRun.error_message }}</span>
                    </div>

                    <div v-if="comparisonFields.length" class="mt-5 space-y-4">
                        <div
                            v-for="field in comparisonFields"
                            :key="field.key"
                            class="prompt-optimize-compare"
                        >
                            <div class="prompt-optimize-compare-header">
                                <div class="font-medium text-[var(--ink)]">{{ field.label }}</div>
                                <div class="text-xs text-[var(--muted)]">
                                    {{ field.source === field.optimized ? 'No content change' : 'Best candidate differs from source' }}
                                </div>
                            </div>

                            <div class="prompt-optimize-compare-grid">
                                <div class="prompt-optimize-compare-card">
                                    <div class="prompt-optimize-compare-label">Source</div>
                                    <pre class="prompt-optimize-code">{{ field.source || 'Empty' }}</pre>
                                </div>

                                <div class="prompt-optimize-compare-card">
                                    <div class="prompt-optimize-compare-label">Best candidate</div>
                                    <pre class="prompt-optimize-code">{{ field.optimized || 'Empty' }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="prompt-optimize-empty">
                <div class="prompt-optimize-empty-icon">
                    <LoaderCircle class="h-4 w-4" />
                </div>
                <div>
                    <div class="font-medium text-[var(--ink)]">No improvement runs yet</div>
                    <div class="mt-1 text-sm leading-6 text-[var(--muted)]">
                        Start with a saved prompt version and active expected scenarios. The best candidate will land here as soon as the first run completes.
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
