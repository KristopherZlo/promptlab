<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UndoBanner from '@/Components/UndoBanner.vue';
import { ChevronDown, KeyRound, ListChecks, Settings2, Trash2 } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { AI_CONNECTION_PRESETS, DEFAULT_AI_CONNECTION_PRESET_ID, findAiConnectionPresetById } from '@/lib/aiConnectionPresets';
import { useUndoableAction } from '@/lib/useUndoableAction';
import { useUrlState } from '@/lib/urlState';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    connections: {
        type: Array,
        required: true,
    },
});

const activeTab = useUrlState({
    key: 'tab',
    defaultValue: 'connections',
    allowedValues: ['connections', 'editor'],
});
const editingConnectionId = ref(null);
const notices = reactive({
    connection: '',
});
const connectionRemoval = useUndoableAction();
const validationState = reactive({
    checked: false,
    testing: false,
    ok: false,
    reachable: false,
    message: '',
    models: [],
});
const providerPresetId = ref(DEFAULT_AI_CONNECTION_PRESET_ID);
const selectedPrimaryModel = ref(findAiConnectionPresetById(DEFAULT_AI_CONNECTION_PRESET_ID)?.defaultModel ?? '');
const selectedDiscoveredModel = ref('');
const driverLabels = {
    openai: 'OpenAI-compatible',
    anthropic: 'Anthropic Claude',
};
const presetSelectOptions = [
    ...AI_CONNECTION_PRESETS.map((preset) => ({
        value: preset.id,
        label: preset.label,
    })),
    {
        value: 'custom',
        label: 'Custom connection',
    },
];

const connectionForm = useForm({
    name: findAiConnectionPresetById(DEFAULT_AI_CONNECTION_PRESET_ID)?.connectionName ?? '',
    driver: findAiConnectionPresetById(DEFAULT_AI_CONNECTION_PRESET_ID)?.driver ?? 'openai',
    base_url: findAiConnectionPresetById(DEFAULT_AI_CONNECTION_PRESET_ID)?.baseUrl ?? 'https://api.openai.com/v1',
    api_key: '',
    models_text: findAiConnectionPresetById(DEFAULT_AI_CONNECTION_PRESET_ID)?.defaultModel ?? 'gpt-5.2',
    is_active: true,
    is_default: props.connections.length === 0,
});

const activeConnections = computed(() => props.connections.filter((connection) => connection.is_active).length);
const totalModels = computed(() =>
    props.connections.reduce((count, connection) => count + (connection.models_json?.length || 0), 0),
);
const activePreset = computed(() => findAiConnectionPresetById(providerPresetId.value));
const quickModelOptions = computed(() => {
    const presetModels = activePreset.value?.models ?? [];

    return selectedPrimaryModel.value && !presetModels.includes(selectedPrimaryModel.value)
        ? [selectedPrimaryModel.value, ...presetModels]
        : presetModels;
});

const resetValidationState = () => {
    validationState.checked = false;
    validationState.testing = false;
    validationState.ok = false;
    validationState.reachable = false;
    validationState.message = '';
    validationState.models = [];
    selectedDiscoveredModel.value = '';
};

const normalizeBaseUrl = (value) => `${value ?? ''}`.trim().replace(/\/+$/, '');

const parseModels = () =>
    `${connectionForm.models_text ?? ''}`
        .split(/[\n,]+/)
        .map((item) => item.trim())
        .filter(Boolean);

const syncDiscoveredModel = (models = []) => {
    const availableModels = Array.isArray(models) ? models : [];
    const preferredModel = [
        selectedDiscoveredModel.value,
        parseModels()[0] ?? '',
        selectedPrimaryModel.value,
    ].find((model) => availableModels.includes(model));

    selectedDiscoveredModel.value = preferredModel ?? availableModels[0] ?? '';
};

const inferPreset = (connectionLike) => AI_CONNECTION_PRESETS.find((preset) =>
    preset.driver === connectionLike.driver
    && normalizeBaseUrl(preset.baseUrl) === normalizeBaseUrl(connectionLike.base_url),
) ?? null;

const syncPresetState = (connection = null) => {
    const matchedPreset = connection
        ? inferPreset(connection)
        : inferPreset({
            driver: connectionForm.driver,
            base_url: connectionForm.base_url,
        });
    const currentModels = connection ? (connection.models_json ?? []) : parseModels();

    providerPresetId.value = matchedPreset?.id ?? 'custom';
    selectedPrimaryModel.value = currentModels.find((model) => matchedPreset?.models.includes(model))
        ?? matchedPreset?.defaultModel
        ?? currentModels[0]
        ?? '';
};

const applyPresetToForm = (presetId, options = {}) => {
    const preset = findAiConnectionPresetById(presetId);

    providerPresetId.value = preset?.id ?? 'custom';

    if (!preset) {
        selectedPrimaryModel.value = parseModels()[0] ?? '';
        resetValidationState();
        return;
    }

    const currentName = `${connectionForm.name ?? ''}`.trim();
    const knownPresetNames = AI_CONNECTION_PRESETS.map(({ connectionName }) => connectionName);
    const shouldReplaceName = options.forceName === true
        || currentName === ''
        || knownPresetNames.includes(currentName);

    selectedPrimaryModel.value = options.selectedModel ?? preset.defaultModel;
    connectionForm.driver = preset.driver;
    connectionForm.base_url = preset.baseUrl;

    if (shouldReplaceName) {
        connectionForm.name = preset.connectionName;
    }

    if (options.replaceModels !== false) {
        connectionForm.models_text = options.useAllModels === true
            ? preset.models.join(', ')
            : selectedPrimaryModel.value;
    }

    resetValidationState();
};

const resetConnectionForm = () => {
    editingConnectionId.value = null;
    connectionForm.reset();
    applyPresetToForm(DEFAULT_AI_CONNECTION_PRESET_ID, {
        forceName: true,
        replaceModels: true,
    });
    connectionForm.is_active = true;
    connectionForm.is_default = props.connections.length === 0;
    connectionForm.clearErrors();
    notices.connection = '';
    resetValidationState();
};

const closeEditor = () => {
    resetConnectionForm();
    activeTab.value = 'connections';
};

const editConnection = (connection) => {
    editingConnectionId.value = connection.id;
    connectionForm.name = connection.name;
    connectionForm.driver = connection.driver;
    connectionForm.base_url = connection.base_url || findAiConnectionPresetById(DEFAULT_AI_CONNECTION_PRESET_ID)?.baseUrl || 'https://api.openai.com/v1';
    connectionForm.api_key = '';
    connectionForm.models_text = (connection.models_json ?? []).join(', ');
    connectionForm.is_active = connection.is_active;
    connectionForm.is_default = connection.is_default;
    connectionForm.clearErrors();
    notices.connection = '';
    syncPresetState(connection);
    resetValidationState();
    activeTab.value = 'editor';
};

watch(
    () => [connectionForm.driver, connectionForm.base_url, connectionForm.api_key, connectionForm.models_text, editingConnectionId.value],
    () => {
        if (validationState.checked) {
            resetValidationState();
        }
    },
);

const applySelectedPreset = () => {
    if (providerPresetId.value === 'custom') {
        selectedPrimaryModel.value = parseModels()[0] ?? '';
        resetValidationState();
        return;
    }

    applyPresetToForm(providerPresetId.value, {
        replaceModels: true,
    });
};

const applySelectedPresetModel = () => {
    if (!selectedPrimaryModel.value) {
        return;
    }

    connectionForm.models_text = selectedPrimaryModel.value;
    resetValidationState();
};

const useAllPresetModels = () => {
    if (!activePreset.value) {
        return;
    }

    connectionForm.models_text = activePreset.value.models.join(', ');
    notices.connection = `Loaded ${activePreset.value.models.length} suggested model${activePreset.value.models.length === 1 ? '' : 's'} from ${activePreset.value.label}.`;
    resetValidationState();
};

const driverLabel = (driver) => driverLabels[driver] ?? driver;

const markPresetAsCustom = () => {
    if (!activePreset.value) {
        syncPresetState();
        resetValidationState();
        return;
    }

    const stillMatchesPreset = connectionForm.driver === activePreset.value.driver
        && normalizeBaseUrl(connectionForm.base_url) === normalizeBaseUrl(activePreset.value.baseUrl);

    if (!stillMatchesPreset) {
        providerPresetId.value = 'custom';
        selectedPrimaryModel.value = parseModels()[0] ?? selectedPrimaryModel.value;
    }

    resetValidationState();
};

const validateConnection = async () => {
    validationState.testing = true;
    notices.connection = '';

    try {
        const response = await axios.post(route('api.llm-connections.validate'), {
            connection_id: editingConnectionId.value,
            driver: connectionForm.driver,
            base_url: connectionForm.base_url || null,
            api_key: connectionForm.api_key || null,
            models_json: parseModels(),
        });

        const result = response.data?.data ?? {};
        validationState.checked = true;
        validationState.ok = !!result.ok;
        validationState.reachable = !!result.reachable;
        validationState.message = result.message || 'Connection test finished.';
        validationState.models = Array.isArray(result.models) ? result.models : [];
        syncDiscoveredModel(validationState.models);
    } catch (error) {
        validationState.checked = true;
        validationState.ok = false;
        validationState.reachable = false;
        validationState.message = extractServerMessage(error, 'Connection test failed.');
        validationState.models = [];
        selectedDiscoveredModel.value = '';
    } finally {
        validationState.testing = false;
    }
};

const importDiscoveredModels = () => {
    if (!validationState.models.length) {
        return;
    }

    connectionForm.models_text = validationState.models.join(', ');
    selectedPrimaryModel.value = validationState.models[0] ?? selectedPrimaryModel.value;
    notices.connection = `Imported ${validationState.models.length} discovered model${validationState.models.length === 1 ? '' : 's'} into the form.`;
};

const applyDiscoveredModel = () => {
    if (!selectedDiscoveredModel.value) {
        return;
    }

    connectionForm.models_text = selectedDiscoveredModel.value;
    selectedPrimaryModel.value = selectedDiscoveredModel.value;
    notices.connection = `Selected ${selectedDiscoveredModel.value} from the latest provider response.`;
};

const saveConnection = async () => {
    connectionForm.processing = true;
    notices.connection = '';

    const payload = {
        name: connectionForm.name,
        driver: connectionForm.driver,
        base_url: connectionForm.base_url || null,
        api_key: connectionForm.api_key || null,
        models_json: parseModels(),
        is_active: !!connectionForm.is_active,
        is_default: !!connectionForm.is_default,
    };

    try {
        if (editingConnectionId.value) {
            await axios.put(route('api.llm-connections.update', editingConnectionId.value), payload);
            notices.connection = 'Connection updated.';
        } else {
            await axios.post(route('api.llm-connections.store'), payload);
            notices.connection = 'Connection created.';
        }

        closeEditor();
        router.reload({ only: ['connections', 'team'] });
    } catch (error) {
        applyServerErrors(connectionForm, error);
        notices.connection = extractServerMessage(error, 'Connection could not be saved.');
    } finally {
        connectionForm.processing = false;
    }
};

const removeConnection = async (connection) => {
    notices.connection = '';

    try {
        await axios.delete(route('api.llm-connections.destroy', connection.id));
        notices.connection = 'Connection removed.';
        router.reload({ only: ['connections', 'team'] });
    } catch (error) {
        notices.connection = extractServerMessage(error, 'Connection removal failed.');
    }
};

const scheduleConnectionRemoval = (connection) => {
    connectionRemoval.scheduleAction({
        label: `${connection.name} will be removed from this workspace.`,
        onCommit: () => removeConnection(connection),
    });
};
</script>

<template>
    <Head title="Model Connections" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Model Connections</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Choose a provider preset, paste the API key, keep the suggested model or replace it, and save the connection once for the whole workspace.
                </p>
            </div>
        </template>

        <div class="page-frame">
            <ToastRelay :message="notices.connection" />

            <div class="page-tabs">
                <button
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === 'connections' }"
                    @click="activeTab = 'connections'"
                >
                    <ListChecks class="h-4 w-4 shrink-0" />
                    <span>Connections</span>
                </button>
                <button
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === 'editor' }"
                    @click="activeTab = 'editor'"
                >
                    <Settings2 class="h-4 w-4 shrink-0" />
                    <span>{{ editingConnectionId ? 'Edit connection' : 'Add connection' }}</span>
                </button>
            </div>

            <div class="page-frame-content">
                <section v-if="activeTab === 'connections'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Connection registry</h2>
                            <p class="text-sm text-[var(--muted)]">Provider settings and default model access for {{ team.name }}.</p>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-6">
                        <div class="summary-strip">
                            <div class="summary-item">
                                <div class="summary-item-label">Workspace</div>
                                <div class="summary-item-value">{{ team.name }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Connections</div>
                                <div class="summary-item-value">{{ connections.length }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Active</div>
                                <div class="summary-item-value">{{ activeConnections }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Models listed</div>
                                <div class="summary-item-value">{{ totalModels }}</div>
                            </div>
                        </div>

                        <UndoBanner
                            v-if="connectionRemoval.pendingAction.active"
                            :label="connectionRemoval.pendingAction.label"
                            :seconds-remaining="connectionRemoval.pendingAction.secondsRemaining"
                            :busy="connectionRemoval.pendingAction.busy"
                            @undo="connectionRemoval.cancelAction"
                            @commit="connectionRemoval.commitAction"
                        />

                        <div class="surface-muted">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Base URL</th>
                                        <th>Models</th>
                                        <th>Driver</th>
                                        <th class="w-[180px]">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="connection in connections" :key="connection.id">
                                        <td>
                                            <div class="font-semibold">{{ connection.name }}</div>
                                            <div class="mt-2 inline-meta text-xs">
                                                <span class="inline-meta-item">
                                                    <KeyRound />
                                                    {{ connection.has_api_key ? 'Key stored' : 'No API key' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                <span class="status-chip">{{ connection.is_active ? 'Active' : 'Inactive' }}</span>
                                                <span v-if="connection.is_default" class="status-chip">Default</span>
                                            </div>
                                        </td>
                                        <td class="mono text-xs">{{ connection.base_url || 'Default API base URL' }}</td>
                                        <td class="text-sm text-[var(--muted)]">
                                            {{ (connection.models_json ?? []).join(', ') || 'No models listed' }}
                                        </td>
                                        <td>{{ driverLabel(connection.driver) }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" class="btn-secondary" @click="editConnection(connection)">Edit</button>
                                                <button
                                                    type="button"
                                                    class="btn-danger btn-icon-only"
                                                    :title="`Delete ${connection.name}`"
                                                    :aria-label="`Delete ${connection.name}`"
                                                    @click="scheduleConnectionRemoval(connection)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="connections.length === 0">
                                        <td colspan="6" class="text-[var(--muted)]">No model connections have been configured for this workspace yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section v-else class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">{{ editingConnectionId ? 'Edit model connection' : 'Add model connection' }}</h2>
                            <p class="text-sm text-[var(--muted)]">Use a provider preset for the common setup, then keep only the model names your workspace should see.</p>
                        </div>
                    </div>

                    <div class="surface-block-body">
                        <form class="grid gap-4" @submit.prevent="saveConnection">
                            <div class="connection-preset-panel">
                                <div class="connection-preset-header">
                                    <div>
                                        <div class="section-title text-base">Provider preset</div>
                                        <p class="mt-1 text-sm text-[var(--muted)]">
                                            Pick a modern provider, paste the key, and start with a suggested model instead of filling every field by hand.
                                        </p>
                                    </div>
                                    <div v-if="activePreset" class="connection-preset-links">
                                        <a
                                            :href="activePreset.docsUrl"
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            class="text-sm"
                                        >
                                            Official docs
                                        </a>
                                        <button type="button" class="btn-secondary" @click="useAllPresetModels">
                                            Use all suggested models
                                        </button>
                                    </div>
                                </div>

                                <div class="connection-preset-grid">
                                    <div>
                                        <label class="field-label">Provider</label>
                                        <div class="select-with-icon">
                                            <select
                                                v-model="providerPresetId"
                                                class="field-select"
                                                @change="applySelectedPreset"
                                            >
                                                <option
                                                    v-for="option in presetSelectOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <ChevronDown class="select-with-icon-caret" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="field-label">Quick model</label>
                                        <div class="select-with-icon">
                                            <select
                                                v-model="selectedPrimaryModel"
                                                class="field-select"
                                                :disabled="!activePreset"
                                                @change="applySelectedPresetModel"
                                            >
                                                <option value="" disabled>Select a model</option>
                                                <option
                                                    v-for="model in quickModelOptions"
                                                    :key="model"
                                                    :value="model"
                                                >
                                                    {{ model }}
                                                </option>
                                            </select>
                                            <ChevronDown class="select-with-icon-caret" />
                                        </div>
                                    </div>
                                </div>

                                <div class="connection-preset-note text-sm text-[var(--muted)]">
                                    {{ activePreset ? activePreset.note : 'Use a custom base URL and choose the runtime manually for a non-standard provider.' }}
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="field-label">Connection name</label>
                                    <input v-model="connectionForm.name" type="text" class="field-input" placeholder="OpenAI Production">
                                    <div v-if="connectionForm.errors.name" class="field-error">{{ connectionForm.errors.name }}</div>
                                </div>
                                <div>
                                    <label class="field-label">Base URL</label>
                                    <input
                                        v-model="connectionForm.base_url"
                                        type="url"
                                        class="field-input"
                                        :placeholder="activePreset?.baseUrl || 'https://api.openai.com/v1'"
                                        @input="markPresetAsCustom"
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="field-label">API key</label>
                                <input v-model="connectionForm.api_key" type="password" class="field-input" placeholder="Leave empty to keep the stored key on update and during tests">
                                <div class="field-hint">Stored encrypted at rest and never sent back to the client after save.</div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="field-label">Runtime</label>
                                    <div class="select-with-icon">
                                        <select v-model="connectionForm.driver" class="field-select" @change="markPresetAsCustom">
                                            <option value="openai">OpenAI-compatible</option>
                                            <option value="anthropic">Anthropic Claude</option>
                                        </select>
                                        <ChevronDown class="select-with-icon-caret" />
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Models shown in the workspace</label>
                                    <input
                                        v-model="connectionForm.models_text"
                                        type="text"
                                        class="field-input"
                                        :placeholder="activePreset?.models.join(', ') || 'gpt-5.2, gpt-5-mini'"
                                    >
                                    <div class="field-hint">Use one model for the simple path, or list several models separated by commas. The connection check below can import one discovered model or the full list.</div>
                                    <div v-if="connectionForm.errors.models_json" class="field-error">{{ connectionForm.errors.models_json }}</div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="check-row">
                                    <input v-model="connectionForm.is_active" type="checkbox">
                                    <div>
                                        <div class="text-sm font-medium">Active</div>
                                        <div class="mt-1 text-xs text-[var(--muted)]">Expose this connection to operational screens.</div>
                                    </div>
                                </label>

                                <label class="check-row">
                                    <input v-model="connectionForm.is_default" type="checkbox">
                                    <div>
                                        <div class="text-sm font-medium">Default</div>
                                        <div class="mt-1 text-xs text-[var(--muted)]">Use this connection as the default workspace provider.</div>
                                    </div>
                                </label>
                            </div>

                            <div class="panel-muted p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="section-title text-base">Connection check</div>
                                        <p class="mt-1 text-sm text-[var(--muted)]">
                                            Validate the credentials and base URL before saving this connection.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn-secondary"
                                        :disabled="validationState.testing"
                                        @click="validateConnection"
                                    >
                                        {{ validationState.testing ? 'Testing...' : 'Test connection' }}
                                    </button>
                                </div>

                                <div v-if="validationState.checked" class="mt-4 space-y-3">
                                    <div class="summary-strip">
                                        <div class="summary-item">
                                            <div class="summary-item-label">Result</div>
                                            <div class="summary-item-value">{{ validationState.ok ? 'Verified' : 'Failed' }}</div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-item-label">Reachable</div>
                                            <div class="summary-item-value">{{ validationState.reachable ? 'Yes' : 'No' }}</div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-item-label">Models returned</div>
                                            <div class="summary-item-value">{{ validationState.models.length }}</div>
                                        </div>
                                    </div>

                                    <div class="text-sm text-[var(--muted)]">
                                        {{ validationState.message }}
                                    </div>

                                    <div v-if="validationState.models.length" class="flex flex-wrap gap-2">
                                        <span
                                            v-for="model in validationState.models.slice(0, 12)"
                                            :key="model"
                                            class="status-chip"
                                        >
                                            {{ model }}
                                        </span>
                                        <span v-if="validationState.models.length > 12" class="status-chip">
                                            +{{ validationState.models.length - 12 }} more
                                        </span>
                                    </div>

                                    <div v-if="validationState.models.length" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                                        <div>
                                            <label class="field-label">Model from provider response</label>
                                            <div class="select-with-icon">
                                                <select v-model="selectedDiscoveredModel" class="field-select">
                                                    <option value="" disabled>Select a discovered model</option>
                                                    <option
                                                        v-for="model in validationState.models"
                                                        :key="model"
                                                        :value="model"
                                                    >
                                                        {{ model }}
                                                    </option>
                                                </select>
                                                <ChevronDown class="select-with-icon-caret" />
                                            </div>
                                            <div class="field-hint">Pick one discovered model for the workspace, or import the whole provider list.</div>
                                        </div>

                                        <div class="flex flex-wrap gap-3">
                                            <button
                                                type="button"
                                                class="btn-secondary"
                                                :disabled="!selectedDiscoveredModel"
                                                @click="applyDiscoveredModel"
                                            >
                                                Use selected model
                                            </button>
                                            <button
                                                type="button"
                                                class="btn-secondary"
                                                @click="importDiscoveredModels"
                                            >
                                                Use all found models
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 border-t border-[var(--line)] pt-4">
                                <button type="button" class="btn-secondary" @click="closeEditor">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="connectionForm.processing">
                                    {{ connectionForm.processing ? 'Saving...' : editingConnectionId ? 'Save changes' : 'Add connection' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
