<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UndoBanner from '@/Components/UndoBanner.vue';
import { KeyRound, ListChecks, Settings2 } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
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

const connectionForm = useForm({
    name: '',
    driver: 'openai',
    base_url: 'https://api.openai.com/v1',
    api_key: '',
    models_text: 'gpt-4.1-mini',
    is_active: true,
    is_default: props.connections.length === 0,
});

const activeConnections = computed(() => props.connections.filter((connection) => connection.is_active).length);
const totalModels = computed(() =>
    props.connections.reduce((count, connection) => count + (connection.models_json?.length || 0), 0),
);

const resetValidationState = () => {
    validationState.checked = false;
    validationState.testing = false;
    validationState.ok = false;
    validationState.reachable = false;
    validationState.message = '';
    validationState.models = [];
};

const resetConnectionForm = () => {
    editingConnectionId.value = null;
    connectionForm.reset();
    connectionForm.driver = 'openai';
    connectionForm.base_url = 'https://api.openai.com/v1';
    connectionForm.models_text = 'gpt-4.1-mini';
    connectionForm.is_active = true;
    connectionForm.is_default = props.connections.length === 0;
    connectionForm.clearErrors();
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
    connectionForm.base_url = connection.base_url || 'https://api.openai.com/v1';
    connectionForm.api_key = '';
    connectionForm.models_text = (connection.models_json ?? []).join(', ');
    connectionForm.is_active = connection.is_active;
    connectionForm.is_default = connection.is_default;
    connectionForm.clearErrors();
    notices.connection = '';
    resetValidationState();
    activeTab.value = 'editor';
};

const parseModels = () =>
    `${connectionForm.models_text ?? ''}`
        .split(/[\n,]+/)
        .map((item) => item.trim())
        .filter(Boolean);

watch(
    () => [connectionForm.driver, connectionForm.base_url, connectionForm.api_key, editingConnectionId.value],
    () => {
        if (validationState.checked) {
            resetValidationState();
        }
    },
);

const validateConnection = async () => {
    validationState.testing = true;
    notices.connection = '';

    try {
        const response = await axios.post(route('api.llm-connections.validate'), {
            connection_id: editingConnectionId.value,
            driver: connectionForm.driver,
            base_url: connectionForm.base_url || null,
            api_key: connectionForm.api_key || null,
        });

        const result = response.data?.data ?? {};
        validationState.checked = true;
        validationState.ok = !!result.ok;
        validationState.reachable = !!result.reachable;
        validationState.message = result.message || 'Connection test finished.';
        validationState.models = Array.isArray(result.models) ? result.models : [];
    } catch (error) {
        validationState.checked = true;
        validationState.ok = false;
        validationState.reachable = false;
        validationState.message = extractServerMessage(error, 'Connection test failed.');
        validationState.models = [];
    } finally {
        validationState.testing = false;
    }
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
    <Head title="AI Connections" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>AI Connections</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Separate the connection registry from the editor so provider configuration is easier to scan and safer to change.
                </p>
            </div>
        </template>

        <div class="page-frame">
            <aside class="page-frame-rail">
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'connections' }"
                    @click="activeTab = 'connections'"
                >
                    <ListChecks class="h-4 w-4 shrink-0" />
                    <span>Connections</span>
                </button>
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'editor' }"
                    @click="activeTab = 'editor'"
                >
                    <Settings2 class="h-4 w-4 shrink-0" />
                    <span>{{ editingConnectionId ? 'Edit connection' : 'New connection' }}</span>
                </button>
            </aside>

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

                        <div v-if="notices.connection" class="notice-banner">
                            {{ notices.connection }}
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
                                        <td>{{ connection.driver }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" class="btn-secondary" @click="editConnection(connection)">Edit</button>
                                                <button type="button" class="btn-ghost text-[var(--danger)]" @click="scheduleConnectionRemoval(connection)">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="connections.length === 0">
                                        <td colspan="6" class="text-[var(--muted)]">No AI connections configured for this workspace yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section v-else class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">{{ editingConnectionId ? 'Edit AI connection' : 'Create AI connection' }}</h2>
                            <p class="text-sm text-[var(--muted)]">Store provider settings once for the current workspace and keep them out of operational screens.</p>
                        </div>
                        <button type="button" class="btn-secondary" @click="closeEditor">Back to list</button>
                    </div>

                    <div class="surface-block-body">
                        <div v-if="notices.connection" class="notice-banner mb-6">
                            {{ notices.connection }}
                        </div>

                        <form class="grid gap-4" @submit.prevent="saveConnection">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="field-label">Connection name</label>
                                    <input v-model="connectionForm.name" type="text" class="field-input" placeholder="OpenAI Production">
                                    <div v-if="connectionForm.errors.name" class="field-error">{{ connectionForm.errors.name }}</div>
                                </div>
                                <div>
                                    <label class="field-label">Base URL</label>
                                    <input v-model="connectionForm.base_url" type="url" class="field-input" placeholder="https://api.openai.com/v1">
                                </div>
                            </div>

                            <div>
                                <label class="field-label">API key</label>
                                <input v-model="connectionForm.api_key" type="password" class="field-input" placeholder="Leave empty to keep the stored key on update and during tests">
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="field-label">Driver</label>
                                    <select v-model="connectionForm.driver" class="field-select">
                                        <option value="openai">openai</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="field-label">Models</label>
                                    <input v-model="connectionForm.models_text" type="text" class="field-input" placeholder="gpt-4.1-mini, gpt-4.1">
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
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 border-t border-[var(--line)] pt-4">
                                <button type="button" class="btn-secondary" @click="closeEditor">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="connectionForm.processing">
                                    {{ connectionForm.processing ? 'Saving...' : editingConnectionId ? 'Save connection' : 'Create connection' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
