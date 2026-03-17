<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { BookCopy, Bot, Braces, FileCode2, FileJson, FileStack, FileText, FlaskConical, Gauge, MessageSquareText, Settings2, Target, User, Workflow } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { formatDateTime, formatScore, parseJsonInput, parseTagList, safeJsonStringify } from '@/lib/formatters';

const props = defineProps({
    promptTemplate: {
        type: Object,
        default: null,
    },
    useCases: {
        type: Array,
        required: true,
    },
    models: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const canManageLibrary = computed(() => (page.props.auth.abilities ?? []).includes('manage_library'));

const taskTypes = ['summarization', 'classification', 'rewrite', 'extraction', 'generation'];
const statuses = ['active', 'draft', 'archived'];

const availableModels = computed(() =>
    props.models.filter((model) => model.available || model.value.startsWith('mock:')),
);

const applyState = (form, state) => {
    Object.entries(state).forEach(([key, value]) => {
        form[key] = value;
    });
};

const templateDefaults = () => ({
    use_case_id: props.promptTemplate?.use_case_id ?? props.useCases[0]?.id ?? '',
    name: props.promptTemplate?.name ?? '',
    description: props.promptTemplate?.description ?? '',
    task_type: props.promptTemplate?.task_type ?? 'summarization',
    status: props.promptTemplate?.status ?? 'active',
    preferred_model: props.promptTemplate?.preferred_model ?? '',
    tags_text: (props.promptTemplate?.tags_json ?? []).join(', '),
});

const versionDefaults = (source = null) => ({
    version_label: source?.version_label ?? '',
    change_summary: source?.change_summary ?? '',
    system_prompt: source?.system_prompt ?? '',
    user_prompt_template: source?.user_prompt_template ?? '',
    variables_schema_text: safeJsonStringify(source?.variables_schema ?? [], '[]'),
    output_type: source?.output_type ?? 'text',
    output_schema_text: safeJsonStringify(source?.output_schema_json ?? {}, '{}'),
    notes: source?.notes ?? '',
    preferred_model: source?.preferred_model ?? props.promptTemplate?.preferred_model ?? '',
});

const templateForm = useForm(templateDefaults());
const versionForm = useForm(versionDefaults());

const selectedVersionId = ref(props.promptTemplate?.versions?.at(-1)?.id ?? null);
const jsonErrors = reactive({
    variables_schema: '',
    output_schema_json: '',
});
const libraryState = reactive({
    recommended_model: '',
    best_for: '',
    usage_notes: '',
    message: '',
    processing: false,
});
const notices = reactive({
    template: '',
    version: '',
});
const activeTab = ref('template');

const versions = computed(() => props.promptTemplate?.versions ?? []);
const versionHistory = computed(() => [...versions.value].reverse());
const currentVersion = computed(() =>
    versions.value.find((version) => version.id === selectedVersionId.value) ?? null,
);
const isDraftVersion = computed(() => !selectedVersionId.value);
const useCaseName = computed(() =>
    props.useCases.find((useCase) => useCase.id === templateForm.use_case_id)?.name ?? 'Unassigned task',
);
const templateTags = computed(() => parseTagList(templateForm.tags_text));
const versionPanelTitle = computed(() =>
    currentVersion.value ? `${currentVersion.value.version_label} revision` : 'New revision draft',
);
const versionPanelSummary = computed(() =>
    currentVersion.value
        ? 'Adjust the instructions, variables, and output validation for this revision.'
        : 'Create the next runnable revision for this template.',
);
const libraryButtonLabel = computed(() => {
    if (libraryState.processing) {
        return 'Saving approval...';
    }

    return currentVersion.value?.library_entry ? 'Update approval' : 'Approve for library';
});

const versionReference = (version) => `#${String(version.id).padStart(4, '0')}`;

const selectVersion = (version) => {
    selectedVersionId.value = version.id;
    applyState(versionForm, versionDefaults(version));
    versionForm.clearErrors();
    jsonErrors.variables_schema = '';
    jsonErrors.output_schema_json = '';
};

const beginNewVersion = (cloneCurrent = false) => {
    const source = cloneCurrent ? currentVersion.value : null;

    selectedVersionId.value = null;
    applyState(versionForm, versionDefaults(source));
    versionForm.version_label = '';
    versionForm.change_summary = '';
    versionForm.clearErrors();
    jsonErrors.variables_schema = '';
    jsonErrors.output_schema_json = '';
};

const syncLibraryState = () => {
    libraryState.recommended_model =
        currentVersion.value?.library_entry?.recommended_model
        ?? currentVersion.value?.preferred_model
        ?? templateForm.preferred_model
        ?? '';
    libraryState.best_for =
        currentVersion.value?.library_entry?.best_for
        ?? props.promptTemplate?.name
        ?? '';
    libraryState.usage_notes =
        currentVersion.value?.library_entry?.usage_notes
        ?? currentVersion.value?.notes
        ?? '';
    libraryState.message = '';
};

watch(currentVersion, syncLibraryState, { immediate: true });

const saveTemplate = async () => {
    templateForm.processing = true;
    templateForm.clearErrors();
    notices.template = '';

    const payload = {
        use_case_id: templateForm.use_case_id,
        name: templateForm.name,
        description: templateForm.description || null,
        task_type: templateForm.task_type,
        status: templateForm.status,
        preferred_model: templateForm.preferred_model || null,
        tags_json: parseTagList(templateForm.tags_text),
    };

    try {
        if (props.promptTemplate) {
            await axios.put(route('api.prompts.update', props.promptTemplate.id), payload);
            notices.template = 'Template details saved.';
            router.reload({ only: ['promptTemplate', 'useCases', 'models'] });
        } else {
            const response = await axios.post(route('api.prompts.store'), payload);
            router.visit(response.data.redirect_url);
        }
    } catch (error) {
        applyServerErrors(templateForm, error);
        notices.template = extractServerMessage(error, 'Template could not be saved.');
    } finally {
        templateForm.processing = false;
    }
};

const versionPayload = () => {
    jsonErrors.variables_schema = '';
    jsonErrors.output_schema_json = '';

    const variablesSchema = parseJsonInput(versionForm.variables_schema_text, []);
    const outputSchema = parseJsonInput(versionForm.output_schema_text, {});

    if (!variablesSchema.valid || !Array.isArray(variablesSchema.value)) {
        jsonErrors.variables_schema = variablesSchema.error || 'Variables schema must be a JSON array.';
        return null;
    }

    if (versionForm.output_type === 'json') {
        const isObject =
            outputSchema.valid
            && outputSchema.value
            && typeof outputSchema.value === 'object'
            && !Array.isArray(outputSchema.value);

        if (!isObject) {
            jsonErrors.output_schema_json = outputSchema.error || 'Output schema must be a JSON object.';
            return null;
        }
    }

    return {
        version_label: versionForm.version_label || null,
        change_summary: versionForm.change_summary || null,
        system_prompt: versionForm.system_prompt || null,
        user_prompt_template: versionForm.user_prompt_template,
        variables_schema: variablesSchema.value,
        output_type: versionForm.output_type,
        output_schema_json: versionForm.output_type === 'json' ? outputSchema.value : {},
        notes: versionForm.notes || null,
        preferred_model: versionForm.preferred_model || null,
    };
};

const saveVersion = async () => {
    if (!props.promptTemplate) {
        return;
    }

    const payload = versionPayload();

    if (!payload) {
        return;
    }

    versionForm.processing = true;
    versionForm.clearErrors();
    notices.version = '';

    try {
        if (currentVersion.value) {
            await axios.put(route('api.prompt-versions.update', currentVersion.value.id), payload);
            notices.version = `${currentVersion.value.version_label} saved.`;
            router.reload({ only: ['promptTemplate'] });
            return;
        }

        const response = await axios.post(route('api.prompt-versions.store', props.promptTemplate.id), payload);
        selectedVersionId.value = response.data.data.id;
        notices.version = 'New revision created.';
        router.reload({ only: ['promptTemplate'] });
    } catch (error) {
        applyServerErrors(versionForm, error);
        notices.version = extractServerMessage(error, 'Revision could not be saved.');
    } finally {
        versionForm.processing = false;
    }
};

const promoteToLibrary = async () => {
    if (!currentVersion.value || !canManageLibrary.value) {
        return;
    }

    libraryState.processing = true;
    libraryState.message = '';

    try {
        await axios.post(route('api.library-entries.store'), {
            prompt_version_id: currentVersion.value.id,
            recommended_model: libraryState.recommended_model || null,
            best_for: libraryState.best_for || null,
            usage_notes: libraryState.usage_notes || null,
        });

        libraryState.message = 'Version promoted to the approved library.';
        router.reload({ only: ['promptTemplate'] });
    } catch (error) {
        libraryState.message = error.response?.data?.message || 'Library promotion failed.';
    } finally {
        libraryState.processing = false;
    }
};
</script>

<template>
    <Head :title="promptTemplate ? promptTemplate.name : 'New Prompt Template'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight">
                        {{ promptTemplate ? promptTemplate.name : 'New Prompt Template' }}
                    </h1>
                    <p class="mt-1 text-sm text-[var(--muted)]">
                        {{ promptTemplate
                            ? 'Edit the template, then switch to versions or approval.'
                            : 'Create the template first.' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link :href="route('prompt-templates.index')" class="btn-secondary">Back to prompts</Link>
                    <Link :href="route('playground')" class="btn-ghost">Open experiments</Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="page-tabs">
                    <button type="button" class="page-tab" :class="{ 'page-tab-active': activeTab === 'template' }" @click="activeTab = 'template'">
                        Template
                    </button>
                    <button
                        type="button"
                        class="page-tab"
                        :class="{ 'page-tab-active': activeTab === 'versions' }"
                        :disabled="!promptTemplate"
                        @click="activeTab = 'versions'"
                    >
                        Versions
                    </button>
                    <button
                        type="button"
                        class="page-tab"
                        :class="{ 'page-tab-active': activeTab === 'library' }"
                        :disabled="!promptTemplate"
                        @click="activeTab = 'library'"
                    >
                        Approval
                    </button>
                </div>
            </section>

            <section v-if="activeTab === 'template' && promptTemplate" class="panel p-5">
                <div class="summary-strip">
                    <div class="summary-item">
                        <div class="summary-item-label">Task</div>
                        <div class="summary-item-value">{{ useCaseName }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Status</div>
                        <div class="summary-item-value capitalize">{{ templateForm.status }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Versions</div>
                        <div class="summary-item-value">{{ versions.length }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Approved</div>
                        <div class="summary-item-value">{{ currentVersion?.library_entry ? 'Yes' : 'No' }}</div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'template'" class="panel p-5">
                <div class="flex items-center justify-between gap-4">
                    <PanelHeader
                        title="Template details"
                        description="Stable metadata for this prompt family."
                        :icon="FileStack"
                    />
                    <button type="button" class="btn-primary" :disabled="templateForm.processing" @click="saveTemplate">
                        {{ templateForm.processing ? 'Saving...' : 'Save template' }}
                    </button>
                </div>

                <div v-if="notices.template" class="notice-banner mt-4">
                    {{ notices.template }}
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                            <label class="field-label">Business task</label>
                        <select v-model="templateForm.use_case_id" class="field-select">
                            <option v-for="useCase in useCases" :key="useCase.id" :value="useCase.id">
                                {{ useCase.name }}
                            </option>
                        </select>
                        <div v-if="templateForm.errors.use_case_id" class="field-error">{{ templateForm.errors.use_case_id }}</div>
                    </div>

                    <div>
                        <label class="field-label">Task type</label>
                        <select v-model="templateForm.task_type" class="field-select">
                            <option v-for="taskType in taskTypes" :key="taskType" :value="taskType">
                                {{ taskType }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Template name</label>
                        <input v-model="templateForm.name" type="text" class="field-input" placeholder="Customer email summarizer">
                        <div v-if="templateForm.errors.name" class="field-error">{{ templateForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="field-label">Status</label>
                        <select v-model="templateForm.status" class="field-select">
                            <option v-for="status in statuses" :key="status" :value="status">
                                {{ status }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Preferred model</label>
                        <select v-model="templateForm.preferred_model" class="field-select">
                            <option value="">No default model</option>
                            <option v-for="model in availableModels" :key="model.value" :value="model.value">
                                {{ model.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Tags</label>
                        <input
                            v-model="templateForm.tags_text"
                            type="text"
                            class="field-input"
                            placeholder="support, json, customer-email"
                        >
                        <div class="field-help">Use short, searchable labels for filtering and documentation.</div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="field-label">Description</label>
                        <textarea
                            v-model="templateForm.description"
                            class="field-textarea"
                            placeholder="Explain what this prompt family should achieve and how the team should use it."
                        />
                    </div>
                </div>

                <div v-if="templateTags.length" class="mt-5">
                    <div class="field-label">Current tags</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span v-for="tag in templateTags" :key="tag" class="status-chip">
                            {{ tag }}
                        </span>
                    </div>
                </div>
            </section>

            <div v-if="promptTemplate && activeTab === 'versions'" class="space-y-6">
                <section class="panel p-5">
                    <div class="version-workbench">
                        <div class="space-y-4">
                            <PanelHeader
                                title="Revision history"
                                description="Newest revisions first. Pick one revision to inspect or edit."
                                :icon="Workflow"
                            />

                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="btn-secondary" @click="beginNewVersion(false)">New revision</button>
                                <button type="button" class="btn-secondary" :disabled="!currentVersion" @click="beginNewVersion(true)">
                                    Duplicate selected
                                </button>
                            </div>

                            <div class="version-log">
                                <button
                                    type="button"
                                    class="version-log-item"
                                    :class="{ 'version-log-item-active': isDraftVersion }"
                                    @click="beginNewVersion(false)"
                                >
                                    <div class="version-log-rail">
                                        <span class="version-log-node" />
                                        <span v-if="versionHistory.length" class="version-log-line" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold">Draft revision</span>
                                                    <span class="version-log-ref">unsaved</span>
                                                </div>
                                                <div class="mt-1 text-sm text-[var(--muted)]">Start a new prompt change from scratch.</div>
                                            </div>
                                        </div>
                                    </div>
                                </button>

                                <button
                                    v-for="(version, index) in versionHistory"
                                    :key="version.id"
                                    type="button"
                                    class="version-log-item"
                                    :class="{ 'version-log-item-active': version.id === selectedVersionId }"
                                    @click="selectVersion(version)"
                                >
                                    <div class="version-log-rail">
                                        <span class="version-log-node" />
                                        <span v-if="index < versionHistory.length - 1" class="version-log-line" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-bold">{{ version.version_label }}</span>
                                                    <span class="version-log-ref">{{ versionReference(version) }}</span>
                                                    <span v-if="version.is_library_approved" class="status-chip">Approved</span>
                                                </div>
                                                <div class="mt-1 text-sm leading-6 text-[var(--muted)]">
                                                    {{ version.change_summary || 'No change summary yet.' }}
                                                </div>
                                            </div>
                                            <div class="text-right text-xs text-[var(--muted)]">
                                                {{ version.created_at ? formatDateTime(version.created_at) : 'No date' }}
                                            </div>
                                        </div>

                                        <div class="version-log-meta">
                                            <span class="inline-meta-item">
                                                <User />
                                                {{ version.created_by || 'Unknown author' }}
                                            </span>
                                            <span class="inline-meta-item">
                                                <FlaskConical />
                                                {{ version.run_count ?? 0 }} runs
                                            </span>
                                            <span class="inline-meta-item">
                                                <Gauge />
                                                {{ formatScore(version.average_score) }} score
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            </div>

                            <div v-if="!versionHistory.length" class="text-sm text-[var(--muted)]">
                                No saved revisions yet. Save the draft to create the first history entry.
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div class="flex items-center justify-between gap-4">
                                <PanelHeader
                                    :title="versionPanelTitle"
                                    :description="versionPanelSummary"
                                    :icon="Settings2"
                                />
                                <button type="button" class="btn-primary" :disabled="versionForm.processing" @click="saveVersion">
                                    {{ versionForm.processing ? 'Saving...' : currentVersion ? 'Save revision' : 'Create revision' }}
                                </button>
                            </div>

                            <div v-if="notices.version" class="notice-banner">
                                {{ notices.version }}
                            </div>

                            <div class="summary-strip">
                                <div class="summary-item">
                                    <div class="summary-item-label">Revision</div>
                                    <div class="summary-item-value">{{ currentVersion?.version_label || 'Draft' }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Reference</div>
                                    <div class="summary-item-value mono">{{ currentVersion ? versionReference(currentVersion) : 'unsaved' }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Created</div>
                                    <div class="summary-item-value">{{ currentVersion?.created_at ? formatDateTime(currentVersion.created_at) : 'Not saved yet' }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Approved</div>
                                    <div class="summary-item-value">{{ currentVersion?.library_entry ? 'Yes' : 'No' }}</div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="field-label">Version label</label>
                                    <input v-model="versionForm.version_label" type="text" class="field-input" placeholder="v4">
                                    <div class="field-help">Leave empty to auto-number the next version on create.</div>
                                </div>

                                <div>
                                    <div class="label-with-icon">
                                        <Bot />
                                        <span>Preferred model override</span>
                                    </div>
                                    <select v-model="versionForm.preferred_model" class="field-select">
                                        <option value="">Use template default</option>
                                        <option v-for="model in availableModels" :key="model.value" :value="model.value">
                                            {{ model.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <div class="label-with-icon">
                                        <MessageSquareText />
                                        <span>Change summary</span>
                                    </div>
                                    <input
                                        v-model="versionForm.change_summary"
                                        type="text"
                                        class="field-input"
                                        placeholder="Short revision message"
                                    >
                                </div>

                                <div class="md:col-span-2">
                                    <div class="label-with-icon">
                                        <FileText />
                                        <span>System prompt</span>
                                    </div>
                                    <textarea
                                        v-model="versionForm.system_prompt"
                                        class="field-textarea"
                                        placeholder="Role, safety limits, domain boundaries, and output instructions"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <div class="label-with-icon">
                                        <FileCode2 />
                                        <span>User prompt template</span>
                                    </div>
                                    <textarea
                                        v-model="versionForm.user_prompt_template"
                                        class="field-textarea"
                                        placeholder="Use variables like {{customer_message}} or {{language}}"
                                    />
                                    <div v-if="versionForm.errors.user_prompt_template" class="field-error">
                                        {{ versionForm.errors.user_prompt_template }}
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <div class="label-with-icon">
                                        <Braces />
                                        <span>Variables schema</span>
                                    </div>
                                    <textarea v-model="versionForm.variables_schema_text" class="field-textarea"></textarea>
                                    <div class="field-help">
                                        JSON array. Example: [{ "name": "language", "required": true, "default": "English" }]
                                    </div>
                                    <div v-if="jsonErrors.variables_schema" class="field-error">{{ jsonErrors.variables_schema }}</div>
                                </div>

                                <div>
                                    <div class="label-with-icon">
                                        <FileJson />
                                        <span>Output type</span>
                                    </div>
                                    <select v-model="versionForm.output_type" class="field-select">
                                        <option value="text">Text</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <div class="label-with-icon">
                                        <FileJson />
                                        <span>Output schema</span>
                                    </div>
                                    <textarea v-model="versionForm.output_schema_text" class="field-textarea"></textarea>
                                    <div class="field-help">
                                        JSON object with optional required keys and primitive types for structured validation.
                                    </div>
                                    <div v-if="jsonErrors.output_schema_json" class="field-error">{{ jsonErrors.output_schema_json }}</div>
                                </div>

                                <div class="md:col-span-2">
                                    <div class="label-with-icon">
                                        <FileText />
                                        <span>Notes for the team</span>
                                    </div>
                                    <textarea
                                        v-model="versionForm.notes"
                                        class="field-textarea"
                                        placeholder="Capture what this version is trying to improve and when it should be used."
                                    />
                                </div>
                            </div>

                            <div class="summary-strip">
                                <div class="summary-item">
                                    <div class="summary-item-label">Average score</div>
                                    <div class="summary-item-value">{{ formatScore(currentVersion?.average_score) }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Runs</div>
                                    <div class="summary-item-value">{{ currentVersion?.run_count ?? 0 }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Evaluations</div>
                                    <div class="summary-item-value">{{ currentVersion?.evaluation_count ?? 0 }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Format pass rate</div>
                                    <div class="summary-item-value">{{ currentVersion?.format_pass_rate != null ? `${currentVersion.format_pass_rate}%` : 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <section v-else-if="promptTemplate && activeTab === 'library'" class="panel p-5">
                <PanelHeader
                    title="Approval and library handoff"
                    description="Approve one revision when the team is ready to reuse it in the shared library."
                    :icon="BookCopy"
                />

                <div v-if="currentVersion" class="summary-strip mt-4">
                    <div class="summary-item">
                        <div class="summary-item-label">Selected version</div>
                        <div class="summary-item-value">{{ currentVersion.version_label }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Average score</div>
                        <div class="summary-item-value">{{ formatScore(currentVersion.average_score) }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Runs</div>
                        <div class="summary-item-value">{{ currentVersion.run_count ?? 0 }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Approval status</div>
                        <div class="summary-item-value">{{ currentVersion.library_entry ? 'Approved' : 'Pending' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Approved by</div>
                        <div class="summary-item-value">{{ currentVersion.library_entry?.approved_by || 'Not approved yet' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Approved at</div>
                        <div class="summary-item-value">{{ currentVersion.library_entry?.approved_at ? formatDateTime(currentVersion.library_entry.approved_at) : 'Not approved yet' }}</div>
                    </div>
                </div>

                <div v-if="currentVersion" class="guide-card mt-5">
                    <div class="font-bold">How approval works</div>
                    <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                        Select one revision, fill the handoff fields below, and click the approval button. Approved revisions appear in the shared library for the whole team.
                    </div>
                </div>

                <div v-if="canManageLibrary && currentVersion" class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="label-with-icon">
                            <Bot />
                            <span>Recommended model</span>
                        </div>
                        <input v-model="libraryState.recommended_model" type="text" class="field-input">
                    </div>

                    <div>
                        <div class="label-with-icon">
                            <Target />
                            <span>Best for</span>
                        </div>
                        <input v-model="libraryState.best_for" type="text" class="field-input">
                    </div>

                    <div class="md:col-span-2">
                        <div class="label-with-icon">
                            <FileText />
                            <span>Usage notes</span>
                        </div>
                        <textarea v-model="libraryState.usage_notes" class="field-textarea"></textarea>
                    </div>
                </div>

                <div v-if="libraryState.message" class="mt-4 text-sm text-[var(--muted)]">
                    {{ libraryState.message }}
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button
                        v-if="canManageLibrary && currentVersion"
                        type="button"
                        class="btn-primary"
                        :disabled="libraryState.processing"
                        @click="promoteToLibrary"
                    >
                        {{ libraryButtonLabel }}
                    </button>
                    <div class="text-sm text-[var(--muted)]">
                        Selected version: <span class="font-bold text-[var(--ink)]">{{ currentVersion?.version_label || 'None' }}</span>
                        <span v-if="currentVersion?.library_entry?.approved_at">
                            | approved by {{ currentVersion.library_entry.approved_by || 'unknown reviewer' }} on {{ formatDateTime(currentVersion.library_entry.approved_at) }}
                        </span>
                    </div>
                </div>
            </section>

            <div v-else-if="activeTab !== 'template'" class="empty-state">
                Save the template first. After that, version work and approval will unlock below.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
