<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import PromptOptimizationPanel from '@/Components/PromptOptimizationPanel.vue';
import PromptQuickTestPanel from '@/Components/PromptQuickTestPanel.vue';
import { BookCopy, Bot, Braces, Clock3, Copy, FileCode2, FileJson, FileStack, FileText, FlaskConical, Gauge, MessageSquareText, Plus, Settings2, Target, User, Workflow } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { formatDateTime, formatScore, parseJsonInput, parseTagList, safeJsonStringify } from '@/lib/formatters';
import { hrefWithQuery, readQueryParam, routeWithQuery, useUrlState } from '@/lib/urlState';

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
    optimizationContext: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const canManageLibrary = computed(() => (page.props.auth.abilities ?? []).includes('manage_library'));
const requestedUseCaseId = Number.parseInt(readQueryParam('use_case_id'), 10);
const requestedVersionId = Number.parseInt(readQueryParam('prompt_version_id'), 10);

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
    use_case_id: props.promptTemplate?.use_case_id
        ?? (props.useCases.some((useCase) => useCase.id === requestedUseCaseId) ? requestedUseCaseId : props.useCases[0]?.id ?? ''),
    name: props.promptTemplate?.name ?? '',
    description: props.promptTemplate?.description ?? '',
    task_type: props.promptTemplate?.task_type ?? '',
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

const selectedVersionId = ref(
    props.promptTemplate?.versions?.some((version) => version.id === requestedVersionId)
        ? requestedVersionId
        : props.promptTemplate?.versions?.at(-1)?.id ?? null,
);
const draftBaseVersionId = ref(null);
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
const tabItems = computed(() => [
    { id: 'template', label: 'Details', icon: FileStack, disabled: false },
    { id: 'versions', label: 'Versions', icon: Workflow, disabled: !props.promptTemplate },
    { id: 'optimize', label: 'Optimize', icon: Target, disabled: !props.promptTemplate },
    { id: 'library', label: 'Shared Library', icon: BookCopy, disabled: !props.promptTemplate },
]);
const activeTab = useUrlState({
    key: 'tab',
    defaultValue: props.promptTemplate && props.promptTemplate.versions?.some((version) => version.id === requestedVersionId)
        ? 'versions'
        : 'template',
    allowedValues: props.promptTemplate
        ? ['template', 'versions', 'optimize', 'library']
        : ['template'],
});

const versions = computed(() => props.promptTemplate?.versions ?? []);
const versionHistory = computed(() => [...versions.value].reverse());
const latestSavedVersion = computed(() => versions.value.at(-1) ?? null);
const approvedHistory = computed(() =>
    versions.value
        .filter((version) => version.library_entry?.id)
        .sort((left, right) => {
            const leftTime = new Date(left.library_entry?.approved_at ?? left.created_at ?? 0).getTime();
            const rightTime = new Date(right.library_entry?.approved_at ?? right.created_at ?? 0).getTime();

            return rightTime - leftTime || right.id - left.id;
        }),
);
const currentApprovedVersion = computed(() => approvedHistory.value[0] ?? null);
const currentVersion = computed(() =>
    versions.value.find((version) => version.id === selectedVersionId.value) ?? null,
);
const isDraftVersion = computed(() => !selectedVersionId.value);
const useCaseName = computed(() =>
    props.useCases.find((useCase) => useCase.id === templateForm.use_case_id)?.name ?? 'Unassigned task',
);
const templateTags = computed(() => parseTagList(templateForm.tags_text));
const templateSaveButtonLabel = computed(() => {
    if (templateForm.processing) {
        return props.promptTemplate ? 'Saving...' : 'Creating...';
    }

    return props.promptTemplate ? 'Save details' : 'Create prompt';
});
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
        return 'Saving to shared library...';
    }

    return currentVersion.value?.library_entry ? 'Update shared version' : 'Save to shared library';
});
const experimentsHref = computed(() =>
    routeWithQuery('playground', {}, {
        mode: 'single',
        use_case_id: templateForm.use_case_id || '',
        prompt_template_id: props.promptTemplate?.id ?? '',
        prompt_version_id: currentVersion.value?.id ?? '',
    }),
);
const versionLibraryHref = (version) =>
    version?.library_entry?.id
        ? route('library.show', version.library_entry.id)
        : route('library.index');
const versionRunHref = (version) =>
    routeWithQuery('playground', {}, {
        mode: 'single',
        use_case_id: templateForm.use_case_id || '',
        prompt_template_id: props.promptTemplate?.id ?? '',
        prompt_version_id: version?.id ?? '',
        model_name: version?.library_entry?.recommended_model ?? version?.preferred_model ?? templateForm.preferred_model ?? '',
    });

const versionReference = (version) => `#${String(version.id).padStart(4, '0')}`;
const versionContentFields = [
    { key: 'change_summary', label: 'Change summary', emptyLabel: 'No change summary', mono: false },
    { key: 'system_prompt', label: 'System prompt', emptyLabel: 'No system prompt', mono: true },
    { key: 'user_prompt_template', label: 'User prompt template', emptyLabel: 'No user prompt template', mono: true },
    { key: 'variables_schema_text', label: 'Variables schema', emptyLabel: '[]', mono: true },
    { key: 'output_type', label: 'Output type', emptyLabel: 'text', mono: true },
    { key: 'output_schema_text', label: 'Output schema', emptyLabel: '{}', mono: true },
    { key: 'preferred_model', label: 'Preferred model', emptyLabel: 'Template default', mono: true },
    { key: 'notes', label: 'Team notes', emptyLabel: 'No team notes', mono: false },
];

const buildVersionSnapshot = (source = null) => ({
    change_summary: source?.change_summary ?? '',
    system_prompt: source?.system_prompt ?? '',
    user_prompt_template: source?.user_prompt_template ?? '',
    variables_schema_text: source ? safeJsonStringify(source.variables_schema ?? [], '[]') : '[]',
    output_type: source?.output_type ?? 'text',
    output_schema_text: source ? safeJsonStringify(source.output_schema_json ?? {}, '{}') : '{}',
    preferred_model: source?.preferred_model ?? '',
    notes: source?.notes ?? '',
});

const editorVersionSnapshot = computed(() => ({
    change_summary: versionForm.change_summary ?? '',
    system_prompt: versionForm.system_prompt ?? '',
    user_prompt_template: versionForm.user_prompt_template ?? '',
    variables_schema_text: versionForm.variables_schema_text ?? '[]',
    output_type: versionForm.output_type ?? 'text',
    output_schema_text: versionForm.output_schema_text ?? '{}',
    preferred_model: versionForm.preferred_model ?? '',
    notes: versionForm.notes ?? '',
}));

const normalizeSnapshotValue = (value) => `${value ?? ''}`.replace(/\r\n/g, '\n').trim();
const displaySnapshotValue = (value, emptyLabel) => {
    const normalized = normalizeSnapshotValue(value);

    return normalized !== '' ? normalized : emptyLabel;
};

const compareVersionSnapshots = (baseSnapshot, nextSnapshot) =>
    versionContentFields
        .map((field) => {
            const previousValue = normalizeSnapshotValue(baseSnapshot?.[field.key]);
            const currentValue = normalizeSnapshotValue(nextSnapshot?.[field.key]);

            if (previousValue === currentValue) {
                return null;
            }

            const changeType = !previousValue && currentValue
                ? 'added'
                : previousValue && !currentValue
                    ? 'removed'
                    : 'updated';

            return {
                ...field,
                changeType,
                changeTypeLabel: changeType === 'added' ? 'Added' : changeType === 'removed' ? 'Removed' : 'Updated',
                previousValue,
                currentValue,
                previousDisplay: displaySnapshotValue(previousValue, field.emptyLabel),
                currentDisplay: displaySnapshotValue(currentValue, field.emptyLabel),
            };
        })
        .filter(Boolean);

const versionIndexById = computed(() =>
    new Map(versions.value.map((version, index) => [version.id, index])),
);
const versionById = computed(() =>
    new Map(versions.value.map((version) => [version.id, version])),
);
const previousVersionFor = (version) => {
    const index = versionIndexById.value.get(version.id);

    return typeof index === 'number' && index > 0 ? versions.value[index - 1] : null;
};
const versionHistoryEntries = computed(() =>
    versionHistory.value.map((version) => {
        const parentVersion = previousVersionFor(version);
        const changes = compareVersionSnapshots(buildVersionSnapshot(parentVersion), buildVersionSnapshot(version));

        return {
            version,
            parentVersion,
            changes,
            changedLabels: changes.slice(0, 3).map((change) => change.label),
            extraChanges: Math.max(changes.length - 3, 0),
            isLatest: latestSavedVersion.value?.id === version.id,
        };
    }),
);
const versionComparisonBase = computed(() =>
    currentVersion.value
        ? previousVersionFor(currentVersion.value)
        : (draftBaseVersionId.value ? versionById.value.get(draftBaseVersionId.value) ?? latestSavedVersion.value : latestSavedVersion.value),
);
const currentVersionChanges = computed(() =>
    compareVersionSnapshots(buildVersionSnapshot(versionComparisonBase.value), editorVersionSnapshot.value),
);
const currentVersionCommitTitle = computed(() =>
    currentVersion.value?.change_summary
    || versionForm.change_summary
    || currentVersion.value?.version_label
    || 'Working draft',
);
const currentVersionDetailCopy = computed(() => {
    if (currentVersion.value) {
        const author = currentVersion.value.created_by || 'Unknown author';
        const createdAt = currentVersion.value.created_at ? formatDateTime(currentVersion.value.created_at) : 'No timestamp';
        const parentLabel = versionComparisonBase.value?.version_label || 'initial state';

        return `${author} saved this revision on ${createdAt}. Comparing against ${parentLabel}.`;
    }

    if (versionComparisonBase.value) {
        return `Unsaved draft compared with ${versionComparisonBase.value.version_label}. Save when the change is ready to join history.`;
    }

    return 'Create the first revision. The history view will start tracking prompt changes after the first save.';
});

const selectVersion = (version) => {
    selectedVersionId.value = version.id;
    draftBaseVersionId.value = null;
    applyState(versionForm, versionDefaults(version));
    versionForm.clearErrors();
    jsonErrors.variables_schema = '';
    jsonErrors.output_schema_json = '';
};

const beginNewVersion = (cloneCurrent = false) => {
    const source = cloneCurrent ? currentVersion.value : null;

    selectedVersionId.value = null;
    draftBaseVersionId.value = source?.id ?? latestSavedVersion.value?.id ?? null;
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

const templateFieldKeys = new Set([
    'use_case_id',
    'name',
    'description',
    'task_type',
    'status',
    'preferred_model',
    'tags_json',
]);

const applyCreatePromptErrors = (error) => {
    const errors = error.response?.data?.errors ?? {};

    templateForm.clearErrors();
    versionForm.clearErrors();

    Object.entries(errors).forEach(([key, value]) => {
        const message = Array.isArray(value) ? value[0] : value;

        if (templateFieldKeys.has(key) || key.startsWith('tags_json')) {
            templateForm.setError(key, message);
            return;
        }

        versionForm.setError(key, message);
    });

    return errors;
};

const saveTemplate = async () => {
    templateForm.processing = true;
    templateForm.clearErrors();
    notices.template = '';
    versionForm.clearErrors();

    const payload = {
        use_case_id: templateForm.use_case_id,
        name: templateForm.name,
        description: templateForm.description || null,
        task_type: templateForm.task_type || null,
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
            const initialVersion = versionPayload();

            if (!initialVersion) {
                return;
            }

            versionForm.processing = true;

            const response = await axios.post(route('api.prompts.store'), {
                ...payload,
                initial_version: initialVersion,
            });
            router.visit(hrefWithQuery(response.data.redirect_url, {
                tab: 'versions',
                prompt_version_id: response.data.first_version_id ?? '',
            }));
        }
    } catch (error) {
        if (props.promptTemplate) {
            applyServerErrors(templateForm, error);
        } else {
            applyCreatePromptErrors(error);
        }
        notices.template = extractServerMessage(error, 'Template could not be saved.');
    } finally {
        templateForm.processing = false;
        versionForm.processing = false;
    }
};

const versionPayload = () => {
    jsonErrors.variables_schema = '';
    jsonErrors.output_schema_json = '';
    versionForm.clearErrors();

    if (`${versionForm.user_prompt_template ?? ''}`.trim() === '') {
        versionForm.setError('user_prompt_template', 'Write the prompt text before saving.');
        return null;
    }

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
        draftBaseVersionId.value = null;
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
    <Head :title="promptTemplate ? promptTemplate.name : 'New Prompt'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight">
                        {{ promptTemplate ? promptTemplate.name : 'New Prompt' }}
                    </h1>
                    <p class="mt-1 text-sm text-[var(--muted)]">
                        {{ promptTemplate
                            ? 'Edit the prompt, test it quickly, and create extra versions only when needed.'
                            : 'Write the prompt and save the first version in one step.' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link :href="route('prompt-templates.index')" class="btn-secondary">Back to prompt list</Link>
                    <Link v-if="promptTemplate && currentVersion" :href="experimentsHref" class="btn-ghost">Open full experiments</Link>
                </div>
            </div>
        </template>

        <div class="page-frame">
            <div class="page-tabs">
                <button
                    v-for="tab in tabItems"
                    :key="tab.id"
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === tab.id }"
                    :disabled="tab.disabled"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                    <span>{{ tab.label }}</span>
                </button>
            </div>

            <div class="page-frame-content">
            <ToastRelay :message="notices.template" />
            <ToastRelay :message="notices.version" />

            <section v-if="activeTab === 'template' && promptTemplate" class="panel p-5">
                <PanelHeader
                    title="Template snapshot"
                    description="Current assignment, status, revision count, and approval state."
                    help="Summarizes the current state of this prompt family so you can see its task alignment and approval progress at a glance."
                />

                <div class="summary-strip mt-4">
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
                    <div class="summary-item">
                        <div class="summary-item-label">Reviewed runs</div>
                        <div class="summary-item-value">{{ promptTemplate.reviewed_runs ?? 0 }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Last review</div>
                        <div class="summary-item-value">{{ promptTemplate.last_reviewed_at ? formatDateTime(promptTemplate.last_reviewed_at) : 'Not reviewed yet' }}</div>
                    </div>
                </div>

                <div v-if="promptTemplate.reviewer_count" class="mt-4 text-sm text-[var(--muted)]">
                    Reviewers involved: {{ promptTemplate.reviewers.join(', ') }}
                </div>
            </section>

            <section v-if="activeTab === 'template'" class="panel p-5">
                <div class="flex items-center justify-between gap-4">
                    <PanelHeader
                        :title="promptTemplate ? 'Prompt details' : 'Prompt setup'"
                        :description="promptTemplate
                            ? 'Stable metadata for this prompt family.'
                            : 'Basic prompt metadata plus the first runnable version.'"
                        :icon="FileStack"
                        :help="promptTemplate
                            ? 'Edits the stable metadata shared by all revisions in this prompt family, including task mapping and default model.'
                            : 'Creates the prompt container and the first runnable version together so you do not need a separate setup step.'"
                    />
                    <button type="button" class="btn-primary" :disabled="templateForm.processing || versionForm.processing" @click="saveTemplate">
                        {{ templateSaveButtonLabel }}
                    </button>
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
                        <label class="field-label">Category (optional)</label>
                        <input
                            v-model="templateForm.task_type"
                            type="text"
                            class="field-input"
                            placeholder="Examples: Support triage, Meeting notes, Billing replies"
                        >
                        <div class="field-help">
                            Use a plain business label only if it helps the team group similar prompts later.
                        </div>
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

                <div v-if="!promptTemplate" class="mt-6 border-t border-[var(--line)] pt-6">
                    <div class="flex items-center justify-between gap-4">
                        <PanelHeader
                            title="First prompt version"
                            description="This becomes the first runnable prompt right away. Extra versions can wait until you actually need them."
                            :icon="Workflow"
                            help="The initial save creates the prompt and v1 together so writing the first prompt does not require a separate versioning step."
                        />
                        <span class="status-chip">Creates v1</span>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="field-label">Version label</label>
                            <input v-model="versionForm.version_label" type="text" class="field-input" placeholder="v1">
                            <div class="field-help">Leave empty to create version `v1` automatically.</div>
                        </div>

                        <div>
                            <div class="label-with-icon">
                                <Bot />
                                <span>Model override</span>
                            </div>
                            <select v-model="versionForm.preferred_model" class="field-select">
                                <option value="">Use prompt default</option>
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
                                placeholder="What is special about this first version?"
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
                                placeholder="Role, boundaries, tone, and output rules"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <div class="label-with-icon">
                                <FileCode2 />
                                <span>Prompt text</span>
                            </div>
                            <textarea
                                v-model="versionForm.user_prompt_template"
                                class="field-textarea"
                                placeholder="Write the actual prompt here. Use variables like {{input_text}} or {{language}} when needed."
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
                                placeholder="Describe how the team should use this first version."
                            />
                        </div>
                    </div>
                </div>
            </section>

            <PromptQuickTestPanel
                v-if="!promptTemplate && activeTab === 'template'"
                :use-case-id="templateForm.use_case_id"
                :task-type="templateForm.task_type"
                :model-name="versionForm.preferred_model || templateForm.preferred_model || ''"
                :system-prompt="versionForm.system_prompt"
                :user-prompt-template="versionForm.user_prompt_template"
                :variables-schema="parseJsonInput(versionForm.variables_schema_text, []).value || []"
                :output-type="versionForm.output_type"
                :output-schema-json="parseJsonInput(versionForm.output_schema_text, {}).value || {}"
                :models="availableModels"
            />

            <div v-if="promptTemplate && activeTab === 'versions'" class="space-y-6">
                <section class="panel p-5">
                    <div class="prompt-history-layout">
                        <aside class="prompt-history-sidebar space-y-4">
                            <PanelHeader
                                title="Revision history"
                                description="Commit-style prompt history with quick context for what changed in each revision."
                                :icon="Workflow"
                                help="Shows the saved revision timeline so the team can reopen prior changes, branch from a prior version, and inspect revision-by-revision changes like a commit view."
                            />

                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="btn-secondary" @click="beginNewVersion(false)">
                                    <Plus class="h-4 w-4" />
                                    <span>New revision</span>
                                </button>
                                <button type="button" class="btn-secondary" :disabled="!currentVersion" @click="beginNewVersion(true)">
                                    <Copy class="h-4 w-4" />
                                    <span>Branch from selected</span>
                                </button>
                            </div>

                            <div class="prompt-history-list">
                                <button
                                    type="button"
                                    class="prompt-history-entry"
                                    :class="{ 'prompt-history-entry-active': isDraftVersion }"
                                    @click="beginNewVersion(false)"
                                >
                                    <div class="prompt-history-entry-graph">
                                        <span class="prompt-history-entry-dot" />
                                        <span v-if="versionHistory.length" class="prompt-history-entry-line" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="prompt-history-entry-title">Working draft</div>
                                        <div class="prompt-history-entry-meta">
                                            <span class="prompt-history-entry-ref">draft</span>
                                            <span v-if="latestSavedVersion" class="prompt-history-entry-ref">
                                                based on {{ draftBaseVersionId ? versionById.get(draftBaseVersionId)?.version_label || latestSavedVersion.version_label : latestSavedVersion.version_label }}
                                            </span>
                                        </div>
                                        <div class="prompt-history-entry-copy">
                                            Start a fresh revision or branch from the selected commit before saving it into history.
                                        </div>
                                    </div>
                                </button>

                                <button
                                    v-for="(entry, index) in versionHistoryEntries"
                                    :key="entry.version.id"
                                    type="button"
                                    class="prompt-history-entry"
                                    :class="{ 'prompt-history-entry-active': entry.version.id === selectedVersionId }"
                                    @click="selectVersion(entry.version)"
                                >
                                    <div class="prompt-history-entry-graph">
                                        <span class="prompt-history-entry-dot" />
                                        <span v-if="index < versionHistoryEntries.length - 1" class="prompt-history-entry-line" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="prompt-history-entry-title">
                                                    {{ entry.version.change_summary || `${entry.version.version_label} revision` }}
                                                </div>
                                                <div class="prompt-history-entry-meta">
                                                    <span class="inline-meta-item">
                                                        <User />
                                                        {{ entry.version.created_by || 'Unknown author' }}
                                                    </span>
                                                    <span class="inline-meta-item">
                                                        <Clock3 />
                                                        {{ entry.version.created_at ? formatDateTime(entry.version.created_at) : 'No date' }}
                                                    </span>
                                                    <span class="prompt-history-entry-ref">{{ entry.version.version_label }}</span>
                                                    <span class="prompt-history-entry-ref">{{ versionReference(entry.version) }}</span>
                                                    <span v-if="entry.isLatest" class="status-chip">Latest</span>
                                                    <span v-if="entry.version.is_library_approved" class="status-chip">Approved</span>
                                                </div>
                                            </div>
                                            <div class="prompt-history-entry-count">{{ entry.changes.length }}</div>
                                        </div>

                                        <div class="prompt-history-entry-metrics">
                                            <span class="inline-meta-item">
                                                <FlaskConical />
                                                {{ entry.version.run_count ?? 0 }} runs
                                            </span>
                                            <span class="inline-meta-item">
                                                <Gauge />
                                                {{ formatScore(entry.version.average_score) }} score
                                            </span>
                                            <span v-if="entry.version.reviewer_count" class="inline-meta-item">
                                                <MessageSquareText />
                                                {{ entry.version.reviewer_count }} reviewers
                                            </span>
                                        </div>

                                        <div v-if="entry.changedLabels.length" class="prompt-history-entry-scopes">
                                            <span v-for="label in entry.changedLabels" :key="`${entry.version.id}-${label}`" class="prompt-history-scope">
                                                {{ label }}
                                            </span>
                                            <span v-if="entry.extraChanges" class="prompt-history-scope">
                                                +{{ entry.extraChanges }} more
                                            </span>
                                        </div>
                                        <div v-else class="prompt-history-entry-copy">
                                            No content changes recorded beyond the default baseline.
                                        </div>
                                    </div>
                                </button>
                            </div>

                            <div v-if="!versionHistory.length" class="text-sm text-[var(--muted)]">
                                No saved revisions yet. Save the draft to create the first history entry.
                            </div>
                        </aside>

                        <div class="space-y-5">
                            <article class="prompt-history-detail">
                                <div class="prompt-history-detail-header">
                                    <div class="min-w-0">
                                        <div class="prompt-history-detail-title-row">
                                            <h3 class="prompt-history-detail-title">{{ currentVersionCommitTitle }}</h3>
                                            <span v-if="currentVersion" class="prompt-history-entry-ref">{{ currentVersion.version_label }}</span>
                                            <span v-if="currentVersion" class="prompt-history-entry-ref">{{ versionReference(currentVersion) }}</span>
                                            <span v-else class="prompt-history-entry-ref">draft</span>
                                            <span v-if="currentVersion?.library_entry" class="status-chip">Approved</span>
                                            <span v-if="currentVersion?.id === latestSavedVersion?.id" class="status-chip">Latest</span>
                                        </div>
                                        <p class="prompt-history-detail-copy">{{ currentVersionDetailCopy }}</p>
                                    </div>

                                    <div class="flex flex-wrap gap-3">
                                        <Link v-if="currentVersion" :href="experimentsHref" class="btn-secondary">Test this version</Link>
                                        <button type="button" class="btn-primary" :disabled="versionForm.processing" @click="saveVersion">
                                            {{ versionForm.processing ? 'Saving...' : currentVersion ? 'Save version' : 'Save as new version' }}
                                        </button>
                                    </div>
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
                                        <div class="summary-item-label">Parent</div>
                                        <div class="summary-item-value">{{ versionComparisonBase?.version_label || 'Initial state' }}</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-item-label">Created</div>
                                        <div class="summary-item-value">{{ currentVersion?.created_at ? formatDateTime(currentVersion.created_at) : 'Not saved yet' }}</div>
                                    </div>
                                </div>

                                <div class="prompt-history-diff">
                                    <div class="prompt-history-diff-header">
                                        <div>
                                            <div class="section-title">Files changed</div>
                                            <p class="mt-1 text-sm text-[var(--muted)]">
                                                Compare this revision with {{ versionComparisonBase?.version_label || 'the initial state' }} before editing further.
                                            </p>
                                        </div>
                                        <div class="prompt-history-entry-count">{{ currentVersionChanges.length }}</div>
                                    </div>

                                    <div v-if="currentVersionChanges.length" class="prompt-history-change-list">
                                        <section
                                            v-for="change in currentVersionChanges"
                                            :key="change.key"
                                            class="prompt-history-change-card"
                                        >
                                            <div class="prompt-history-change-header">
                                                <div class="font-semibold text-[var(--ink)]">{{ change.label }}</div>
                                                <span
                                                    class="prompt-history-change-status"
                                                    :class="`prompt-history-change-status-${change.changeType}`"
                                                >
                                                    {{ change.changeTypeLabel }}
                                                </span>
                                            </div>

                                            <div class="prompt-history-change-grid">
                                                <div class="prompt-history-change-pane prompt-history-change-pane-old">
                                                    <div class="prompt-history-change-pane-label">
                                                        {{ versionComparisonBase?.version_label || 'Initial state' }}
                                                    </div>
                                                    <pre
                                                        class="prompt-history-change-body"
                                                        :class="{ 'prompt-history-change-body-mono': change.mono }"
                                                    >{{ change.previousDisplay }}</pre>
                                                </div>
                                                <div class="prompt-history-change-pane prompt-history-change-pane-new">
                                                    <div class="prompt-history-change-pane-label">
                                                        {{ currentVersion?.version_label || 'Draft' }}
                                                    </div>
                                                    <pre
                                                        class="prompt-history-change-body"
                                                        :class="{ 'prompt-history-change-body-mono': change.mono }"
                                                    >{{ change.currentDisplay }}</pre>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <div v-else class="prompt-history-empty">
                                        No content changes yet. Update the editor below to build a meaningful revision diff.
                                    </div>
                                </div>
                            </article>

                            <PanelHeader
                                :title="versionPanelTitle"
                                :description="versionPanelSummary"
                                :icon="Settings2"
                                help="Edits one specific revision, including prompt text, variables, output validation, and revision notes."
                            />

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
                                    <div class="summary-item-label">Reviewed runs</div>
                                    <div class="summary-item-value">{{ currentVersion?.reviewed_runs ?? 0 }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Reviewers</div>
                                    <div class="summary-item-value">{{ currentVersion?.reviewer_count ?? 0 }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Format pass rate</div>
                                    <div class="summary-item-value">{{ currentVersion?.format_pass_rate != null ? `${currentVersion.format_pass_rate}%` : 'N/A' }}</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-item-label">Last review</div>
                                    <div class="summary-item-value">{{ currentVersion?.last_reviewed_at ? formatDateTime(currentVersion.last_reviewed_at) : 'Not reviewed yet' }}</div>
                                </div>
                            </div>

                                <div v-if="currentVersion?.reviewer_count" class="text-sm text-[var(--muted)]">
                                    Reviewers involved: {{ currentVersion.reviewers?.join(', ') || 'Reviewer names not recorded.' }}
                                </div>
                        </div>
                    </div>
                </section>

                <PromptQuickTestPanel
                    :use-case-id="templateForm.use_case_id"
                    :task-type="templateForm.task_type"
                    :model-name="versionForm.preferred_model || templateForm.preferred_model || ''"
                    :system-prompt="versionForm.system_prompt"
                    :user-prompt-template="versionForm.user_prompt_template"
                    :variables-schema="parseJsonInput(versionForm.variables_schema_text, []).value || []"
                    :output-type="versionForm.output_type"
                    :output-schema-json="parseJsonInput(versionForm.output_schema_text, {}).value || {}"
                    :models="availableModels"
                />
            </div>

            <PromptOptimizationPanel
                v-else-if="promptTemplate && activeTab === 'optimize'"
                :prompt-template="promptTemplate"
                :versions="versions"
                :models="availableModels"
                :optimization-context="optimizationContext"
                :suggested-source-version-id="currentVersion?.id ?? latestSavedVersion?.id ?? null"
            />

            <section v-else-if="promptTemplate && activeTab === 'library'" class="panel p-5">
                <PanelHeader
                    title="Approval and library handoff"
                    description="Approve one revision when the team is ready to reuse it in the shared library."
                    :icon="BookCopy"
                    help="This is the final handoff area where one revision is promoted into the approved library for broader reuse."
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

                <div v-if="currentApprovedVersion" class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <BookCopy />
                            <span>Current approved source</span>
                        </div>

                        <div class="summary-list mt-4">
                            <div class="summary-row">
                                <span>Version</span>
                                <span>{{ currentApprovedVersion.version_label }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Approved by</span>
                                <span>{{ currentApprovedVersion.library_entry?.approved_by || 'Unknown reviewer' }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Approved at</span>
                                <span>{{ currentApprovedVersion.library_entry?.approved_at ? formatDateTime(currentApprovedVersion.library_entry.approved_at) : 'Not recorded' }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Recommended model</span>
                                <span class="mono text-xs">{{ currentApprovedVersion.library_entry?.recommended_model || currentApprovedVersion.preferred_model || 'No override' }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Best for</span>
                                <span>{{ currentApprovedVersion.library_entry?.best_for || 'General internal use' }}</span>
                            </div>
                        </div>

                        <div class="mt-4 text-sm leading-6 text-[var(--muted)]">
                            {{ currentApprovedVersion.library_entry?.usage_notes || currentApprovedVersion.notes || 'No usage notes recorded for the current library source.' }}
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3 text-sm">
                            <button
                                v-if="currentVersion?.id !== currentApprovedVersion.id"
                                type="button"
                                class="app-inline-link"
                                @click="selectVersion(currentApprovedVersion)"
                            >
                                Select approved version
                            </button>
                            <Link :href="versionLibraryHref(currentApprovedVersion)" class="app-inline-link">Open saved version</Link>
                            <Link :href="versionRunHref(currentApprovedVersion)" class="app-inline-link">Test saved version</Link>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <Workflow />
                            <span>Approval history</span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <button
                                v-for="version in approvedHistory"
                                :key="`approval-${version.id}`"
                                type="button"
                                class="guide-card w-full text-left"
                                @click="selectVersion(version)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-bold">{{ version.version_label }}</div>
                                        <div class="mt-1 text-sm text-[var(--muted)]">
                                            {{ version.library_entry?.approved_by || 'Unknown reviewer' }}
                                        </div>
                                    </div>
                                    <div class="text-sm text-[var(--muted)]">
                                        {{ version.library_entry?.approved_at ? formatDateTime(version.library_entry.approved_at) : 'No date' }}
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                                    <span class="status-chip">Approved</span>
                                    <span class="mono text-xs text-[var(--muted)]">{{ version.library_entry?.recommended_model || version.preferred_model || 'No override' }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state mt-5">
                    No versions from this template have been approved for library reuse yet.
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
        </div>
    </AuthenticatedLayout>
</template>
