<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import {
    ArrowLeft,
    ArrowRight,
    Bot,
    Eye,
    FileCode2,
    FileText,
    FileJson,
    FlaskConical,
    Gauge,
    Layers3,
    ListChecks,
    Play,
    Settings2,
    Variable,
} from 'lucide-vue-next';
import {
    buildPromptPreview,
    formatDateTime,
    formatScore,
    safeJsonStringify,
    truncateText,
} from '@/lib/formatters';
import { hrefWithQuery, readQueryList, readQueryParam, useUrlState } from '@/lib/urlState';

const props = defineProps({
    useCases: {
        type: Array,
        required: true,
    },
    models: {
        type: Array,
        required: true,
    },
    recentExperiments: {
        type: Array,
        required: true,
    },
});

const requestedMode = readQueryParam('mode');
const requestedUseCaseId = Number.parseInt(readQueryParam('use_case_id'), 10);
const requestedTemplateId = Number.parseInt(readQueryParam('prompt_template_id'), 10);
const requestedVersionIds = readQueryList('prompt_version_id')
    .map((value) => Number.parseInt(value, 10))
    .filter((value) => Number.isInteger(value) && value > 0);
const allPromptTemplates = props.useCases.flatMap((useCase) =>
    (useCase.prompt_templates ?? []).map((template) => ({
        ...template,
        use_case_id: useCase.id,
    })),
);
const requestedTemplate = allPromptTemplates.find((template) => template.id === requestedTemplateId) ?? null;
const requestedVersion = requestedVersionIds.length > 0
    ? allPromptTemplates
        .flatMap((template) => (template.versions ?? []).map((version) => ({
            ...version,
            use_case_id: template.use_case_id,
        })))
        .find((version) => version.id === requestedVersionIds[0]) ?? null
    : null;
const availableModels = computed(() =>
    props.models.filter((model) => model.available || model.value.startsWith('mock:')),
);

const defaultUseCaseId = props.useCases.some((useCase) => useCase.id === requestedUseCaseId)
    ? requestedUseCaseId
    : requestedVersion?.use_case_id
        ?? requestedTemplate?.use_case_id
        ?? '';
const defaultModel = '';
const defaultMode = ['single', 'compare', 'batch'].includes(requestedMode) ? requestedMode : 'single';
const defaultPromptVersionIds = requestedVersionIds.length > 0
    ? requestedVersionIds
    : [];

const form = reactive({
    use_case_id: defaultUseCaseId,
    mode: defaultMode,
    prompt_version_ids: defaultPromptVersionIds,
    input_text: '',
    variables: {},
    test_case_ids: [],
    model_name: defaultModel,
    temperature: 0.2,
    max_tokens: 700,
});

const submitting = ref(false);
const errors = reactive({});
const stepOrder = ['setup', 'versions', 'input', 'review'];
const activeStep = useUrlState({
    key: 'step',
    defaultValue: 'setup',
    allowedValues: stepOrder,
});

const selectedUseCase = computed(() =>
    props.useCases.find((useCase) => useCase.id === Number(form.use_case_id)) ?? null,
);

const versionOptions = computed(() =>
    (selectedUseCase.value?.prompt_templates ?? []).flatMap((template) =>
        (template.versions ?? []).map((version) => ({
            ...version,
            template_id: template.id,
            template_name: template.name,
            template_description: template.description,
            task_type: template.task_type,
            effective_model: version.preferred_model || template.preferred_model || '',
        })),
    ),
);

const testCaseOptions = computed(() => selectedUseCase.value?.test_cases ?? []);
const maxPromptCount = computed(() => (form.mode === 'compare' ? 3 : 1));
const activePromptVersions = computed(() =>
    form.prompt_version_ids
        .map((id) => versionOptions.value.find((version) => version.id === id))
        .filter(Boolean),
);
const primaryVersion = computed(() => activePromptVersions.value[0] ?? null);
const variableSchema = computed(() => primaryVersion.value?.variables_schema ?? []);
const selectedTestCases = computed(() =>
    testCaseOptions.value.filter((testCase) => form.test_case_ids.includes(testCase.id)),
);
const promptPreview = computed(() =>
    buildPromptPreview(primaryVersion.value, form.input_text, form.variables),
);
const runButtonLabel = computed(() => {
    if (submitting.value) {
        return 'Starting...';
    }

    if (form.mode === 'batch') {
        return 'Start batch run';
    }

    if (form.mode === 'compare') {
        return 'Run compare';
    }

    return 'Run prompt';
});

const modeGuide = computed(() => {
    if (form.mode === 'compare') {
        return {
            title: 'Compare mode',
            body: 'Run the same input through two or three prompt versions before the team decides which wording is stronger.',
        };
    }

    if (form.mode === 'batch') {
        return {
            title: 'Batch mode',
            body: 'Run one version against a saved set of test cases when a candidate version needs broader validation.',
        };
    }

    return {
        title: 'Single mode',
        body: 'Run one version on one realistic input when you want the fastest read on prompt behavior.',
    };
});

const modeGuideIcon = computed(() => {
    if (form.mode === 'compare') {
        return ListChecks;
    }

    if (form.mode === 'batch') {
        return Layers3;
    }

    return Play;
});

const selectionStats = computed(() => ({
    promptCount: form.prompt_version_ids.length,
    batchCount: form.test_case_ids.length,
}));

const activeStepIndex = computed(() => stepOrder.indexOf(activeStep.value));
const previousStep = computed(() => stepOrder[activeStepIndex.value - 1] ?? null);
const nextStep = computed(() => stepOrder[activeStepIndex.value + 1] ?? null);
const canGoBack = computed(() => previousStep.value !== null);
const canGoNext = computed(() => nextStep.value !== null);
const stepTabs = [
    { id: 'setup', label: 'Setup', icon: Settings2 },
    { id: 'versions', label: 'Versions', icon: ListChecks },
    { id: 'input', label: 'Input', icon: Variable },
    { id: 'review', label: 'Review', icon: Play },
];

const syncPromptSelection = () => {
    const validIds = versionOptions.value.map((version) => version.id);
    const selected = form.prompt_version_ids.filter((id) => validIds.includes(id));

    form.prompt_version_ids = [...new Set(selected)].slice(0, maxPromptCount.value);

    if (form.mode !== 'batch') {
        form.test_case_ids = [];
        return;
    }

    const validTestCaseIds = testCaseOptions.value.map((testCase) => testCase.id);
    const selectedCases = form.test_case_ids.filter((id) => validTestCaseIds.includes(id));

    form.test_case_ids = [...new Set(selectedCases)];
};

const syncVariableInputs = () => {
    const currentValues = { ...(form.variables ?? {}) };
    const nextValues = {};

    for (const field of variableSchema.value) {
        if (!field?.name) {
            continue;
        }

        nextValues[field.name] = currentValues[field.name] ?? field.default ?? '';
    }

    form.variables = nextValues;
};

watch([selectedUseCase, () => form.mode], syncPromptSelection, { immediate: true });
watch(variableSchema, syncVariableInputs, { immediate: true });

const clearErrors = () => {
    Object.keys(errors).forEach((key) => delete errors[key]);
};

const togglePromptVersion = (id) => {
    const selected = form.prompt_version_ids.includes(id);

    if (selected) {
        form.prompt_version_ids = form.prompt_version_ids.filter((value) => value !== id);
        syncPromptSelection();
        return;
    }

    if (form.mode !== 'compare') {
        form.prompt_version_ids = [id];
        return;
    }

    if (form.prompt_version_ids.length >= 3) {
        form.prompt_version_ids = [...form.prompt_version_ids.slice(1), id];
        return;
    }

    form.prompt_version_ids = [...form.prompt_version_ids, id];
};

const toggleTestCase = (id) => {
    if (form.test_case_ids.includes(id)) {
        form.test_case_ids = form.test_case_ids.filter((value) => value !== id);
        return;
    }

    form.test_case_ids = [...form.test_case_ids, id].slice(0, 50);
};

const goToPreviousStep = () => {
    if (previousStep.value) {
        activeStep.value = previousStep.value;
    }
};

const goToNextStep = () => {
    if (nextStep.value) {
        activeStep.value = nextStep.value;
    }
};

const submit = async () => {
    clearErrors();
    submitting.value = true;

    const payload = {
        mode: form.mode,
        prompt_version_ids: form.prompt_version_ids,
        input_text: form.mode === 'batch' ? null : form.input_text,
        variables: Object.fromEntries(
            Object.entries(form.variables ?? {}).filter(([, value]) => `${value ?? ''}`.trim() !== ''),
        ),
        test_case_ids: form.mode === 'batch' ? form.test_case_ids : [],
        model_name: form.model_name,
        temperature: Number(form.temperature),
        max_tokens: Number(form.max_tokens),
    };

    try {
        const response = await axios.post(route('api.experiments.store'), payload);
        router.visit(hrefWithQuery(response.data.redirect_url, { tab: 'results' }));
    } catch (error) {
        const serverErrors = error.response?.data?.errors ?? {};

        Object.entries(serverErrors).forEach(([key, value]) => {
            errors[key] = Array.isArray(value) ? value[0] : value;
        });
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <Head title="Experiments" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Experiments</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">Configure and launch one experiment step by step.</p>
            </div>
        </template>

        <div class="page-frame">
            <aside class="page-frame-rail">
                <button
                    v-for="(tab, index) in stepTabs"
                    :key="tab.id"
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeStep === tab.id }"
                    @click="activeStep = tab.id"
                >
                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                    <span>{{ index + 1 }}. {{ tab.label }}</span>
                </button>

                <Link :href="route('admin.ai-connections')" class="page-frame-tab">
                    <Bot class="h-4 w-4 shrink-0" />
                    <span>AI connections</span>
                </Link>
                <Link :href="route('prompt-templates.index')" class="page-frame-tab">
                    <FileCode2 class="h-4 w-4 shrink-0" />
                    <span>Prompt templates</span>
                </Link>
            </aside>

            <div class="page-frame-content">

            <section v-if="activeStep === 'setup'" class="panel p-5">
                <PanelHeader
                    title="1. Experiment setup"
                    description="Choose the task, the experiment mode, and the runtime settings first."
                    :icon="Settings2"
                    help="Defines the business task, experiment mode, model, and runtime settings that will apply to the run."
                />

                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="field-label">Task</label>
                        <select v-model="form.use_case_id" class="field-select">
                            <option value="">Select task</option>
                            <option v-for="useCase in useCases" :key="useCase.id" :value="useCase.id">
                                {{ useCase.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Mode</label>
                        <select v-model="form.mode" class="field-select">
                            <option value="single">Single</option>
                            <option value="compare">Compare</option>
                            <option value="batch">Batch</option>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Model</label>
                        <select v-model="form.model_name" class="field-select">
                            <option value="">Select model</option>
                            <option v-for="model in availableModels" :key="model.value" :value="model.value">
                                {{ model.label }}
                            </option>
                        </select>
                        <div class="field-help">
                            Real API models come from
                            <Link :href="route('admin.ai-connections')" class="font-bold text-[var(--accent)] hover:underline">AI Connections</Link>.
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Temperature</label>
                        <input v-model="form.temperature" type="number" min="0" max="2" step="0.1" class="field-input">
                    </div>

                    <div>
                        <label class="field-label">Max tokens</label>
                        <input v-model="form.max_tokens" type="number" min="64" max="4096" step="1" class="field-input">
                    </div>
                </div>

                <div class="mt-4 guide-card">
                    <div class="text-block-title">
                        <component :is="modeGuideIcon" />
                        <span>{{ modeGuide.title }}</span>
                    </div>
                    <div class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ modeGuide.body }}</div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--line)] pt-4">
                    <div class="text-sm text-[var(--muted)]">Step 1 of 4</div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" :disabled="!canGoBack" @click="goToPreviousStep">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </button>
                        <button type="button" class="btn-primary" :disabled="!canGoNext" @click="goToNextStep">
                            Next
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeStep === 'versions'" class="panel p-5">
                <div class="flex items-center justify-between gap-4">
                    <PanelHeader
                        title="2. Prompt versions"
                        :description="form.mode === 'compare'
                            ? 'Select two or three versions for side-by-side review.'
                            : 'Select the version this experiment should execute.'"
                        :icon="ListChecks"
                        help="Selects the prompt versions this experiment will execute. In compare mode this is where the candidates are chosen side by side."
                    />
                    <div class="text-sm text-[var(--muted)]">
                        {{ selectionStats.promptCount }}/{{ maxPromptCount }} selected
                    </div>
                </div>

                <div v-if="versionOptions.length" class="mt-4 space-y-3">
                    <label v-for="version in versionOptions" :key="version.id" class="check-row">
                        <input
                            type="checkbox"
                            :checked="form.prompt_version_ids.includes(version.id)"
                            @change="togglePromptVersion(version.id)"
                        >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-bold">
                                        {{ version.template_name }}
                                        <span class="text-[var(--muted)]">{{ version.version_label }}</span>
                                    </div>
                                    <div class="mt-1 text-sm leading-6 text-[var(--muted)]">
                                        {{ version.change_summary || version.template_description || 'No summary yet.' }}
                                    </div>
                                </div>
                                <span class="status-chip">{{ version.output_type }}</span>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-[var(--muted)]">
                                <span class="inline-meta-item">
                                    <ListChecks />
                                    {{ version.task_type }}
                                </span>
                                <span v-if="version.effective_model" class="inline-meta-item mono">
                                    <Bot />
                                    {{ version.effective_model }}
                                </span>
                                <span class="inline-meta-item">
                                    <Gauge />
                                    {{ formatScore(version.average_score) }} avg score
                                </span>
                            </div>
                        </div>
                    </label>
                </div>

                <div v-else-if="selectedUseCase" class="empty-state mt-4">
                    This task does not have prompt versions yet. Create one in Prompt Templates first.
                </div>

                <div v-else class="empty-state mt-4">
                    Select a task first to load available prompt versions.
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--line)] pt-4">
                    <div class="text-sm text-[var(--muted)]">Step 2 of 4</div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" :disabled="!canGoBack" @click="goToPreviousStep">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </button>
                        <button type="button" class="btn-primary" :disabled="!canGoNext" @click="goToNextStep">
                            Next
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeStep === 'input'" class="panel p-5">
                <PanelHeader
                    :title="form.mode === 'batch' ? '3. Batch cases' : '3. Input and variables'"
                    :description="form.mode === 'batch'
                        ? 'Select the saved cases that should be included in the batch run.'
                        : 'Paste a realistic business example and fill the variables required by the selected version.'"
                    :icon="Variable"
                    help="Provides the real business input or the saved batch cases that will be sent to the selected prompt version."
                />

                <div v-if="form.mode !== 'batch'" class="mt-4">
                    <label class="field-label">Input text</label>
                    <textarea
                        v-model="form.input_text"
                        class="field-textarea"
                        placeholder="Paste a customer message, meeting notes, ticket text, or another realistic example."
                    />
                </div>

                <div v-else-if="testCaseOptions.length" class="mt-4 space-y-3">
                    <label v-for="testCase in testCaseOptions" :key="testCase.id" class="check-row">
                        <input
                            type="checkbox"
                            :checked="form.test_case_ids.includes(testCase.id)"
                            @change="toggleTestCase(testCase.id)"
                        >
                        <div class="min-w-0 flex-1">
                            <div class="font-bold">{{ testCase.title }}</div>
                            <div class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ truncateText(testCase.input_text, 160) }}</div>
                        </div>
                    </label>
                </div>

                <div v-else-if="form.mode === 'batch' && selectedUseCase" class="empty-state mt-4">
                    This task does not have saved test cases yet.
                </div>

                <div v-else-if="form.mode === 'batch'" class="empty-state mt-4">
                    Select a task first to load saved test cases.
                </div>

                <div v-if="variableSchema.length" class="mt-5">
                    <div class="label-with-icon mb-0">
                        <Variable />
                        <span>Variables</span>
                    </div>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div v-for="field in variableSchema" :key="field.name">
                            <label class="field-label">{{ field.label || field.name }}</label>
                            <input
                                v-model="form.variables[field.name]"
                                type="text"
                                class="field-input"
                                :placeholder="field.required ? 'Required' : 'Optional'"
                            >
                            <div class="field-help">
                                {{ field.required ? 'Required variable' : 'Optional variable' }}
                                <span v-if="field.default">. Default: {{ field.default }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--line)] pt-4">
                    <div class="text-sm text-[var(--muted)]">Step 3 of 4</div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" :disabled="!canGoBack" @click="goToPreviousStep">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </button>
                        <button type="button" class="btn-primary" :disabled="!canGoNext" @click="goToNextStep">
                            Next
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeStep === 'review'" class="panel p-5">
                <PanelHeader
                    title="4. Preview and run"
                    description="Review the selection summary and assembled prompt before launching the experiment."
                    :icon="Play"
                    help="Final checkpoint before execution. Review selections, compiled prompt content, and batch scope here before starting the run."
                />

                <div v-if="errors.prompt_version_ids || errors.input_text || errors.test_case_ids" class="mt-4 rounded-[8px] border border-[var(--danger)]/20 bg-[rgba(224,30,90,0.08)] px-4 py-3 text-sm text-[var(--danger)]">
                    {{ errors.prompt_version_ids || errors.input_text || errors.test_case_ids }}
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="panel-muted p-4">
                        <div class="flex items-start gap-3">
                            <Eye class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                            <div class="w-full">
                                <div class="font-bold">Selection summary</div>

                                <div class="mt-3 space-y-3">
                                    <div>
                                        <div class="inline-meta-item text-sm font-bold text-[var(--muted)]">
                                            <Layers3 />
                                            <span>Selected versions</span>
                                        </div>
                                        <div class="mt-2 space-y-2">
                                            <div v-for="version in activePromptVersions" :key="version.id" class="guide-card">
                                                <div class="font-bold">{{ version.template_name }} {{ version.version_label }}</div>
                                                <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                                    {{ version.change_summary || 'No change summary.' }}
                                                </div>
                                            </div>
                                            <div v-if="activePromptVersions.length === 0" class="text-sm text-[var(--muted)]">
                                                No versions selected yet.
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="primaryVersion?.output_type === 'json'">
                                        <div class="flex items-center gap-2 text-sm font-bold text-[var(--muted)]">
                                            <FileJson class="h-4 w-4" />
                                            Expected JSON shape
                                        </div>
                                        <pre class="code-block mt-2">{{ safeJsonStringify(primaryVersion.output_schema_json, '{}') }}</pre>
                                    </div>

                                    <div v-if="form.mode === 'batch'">
                                        <div class="inline-meta-item text-sm font-bold text-[var(--muted)]">
                                            <ListChecks />
                                            <span>Batch scope</span>
                                        </div>
                                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                            {{ selectedTestCases.length }} saved cases selected.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <FileCode2 />
                            <span>Assembled prompt preview</span>
                        </div>
                        <pre class="code-block mt-3">{{ promptPreview || 'Select a prompt version to preview the compiled prompt.' }}</pre>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--line)] pt-4">
                    <div class="text-sm text-[var(--muted)]">Step 4 of 4</div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" :disabled="!canGoBack" @click="goToPreviousStep">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </button>
                        <button type="button" class="btn-primary" :disabled="submitting || !form.prompt_version_ids.length" @click="submit">
                            {{ runButtonLabel }}
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="activeStep === 'review'" class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Recent experiments"
                        description="Open one when you want to continue review or compare with the run you are about to start."
                        :icon="FlaskConical"
                        help="Shows recent experiment runs so you can reopen prior work or compare the new run with recent output."
                    />
                </div>

                <div class="divide-y divide-[var(--line)]">
                    <div v-for="experiment in recentExperiments" :key="experiment.id" class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <Link :href="route('experiments.show', experiment.id)" class="font-bold hover:underline">
                                    {{ experiment.use_case?.name || 'Ad hoc experiment' }}
                                </Link>
                                <div class="mt-2 inline-meta text-xs">
                                    <span class="inline-meta-item">
                                        <ListChecks />
                                        {{ experiment.mode }}
                                    </span>
                                    <span class="inline-meta-item">
                                        <Bot />
                                        {{ experiment.model_name }}
                                    </span>
                                </div>
                                <div class="mt-1 inline-meta text-xs">
                                    <span class="inline-meta-item">
                                        <FileText />
                                        {{ formatDateTime(experiment.created_at) }}
                                    </span>
                                </div>
                            </div>
                            <span class="status-chip">{{ experiment.status }}</span>
                        </div>
                    </div>

                    <div v-if="recentExperiments.length === 0" class="px-5 py-5 text-sm text-[var(--muted)]">
                        No recent experiments yet.
                    </div>
                </div>
            </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
