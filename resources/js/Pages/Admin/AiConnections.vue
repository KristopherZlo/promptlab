<script setup>
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import UndoBanner from '@/Components/UndoBanner.vue';
import { Bot, KeyRound, Link2, Server, Settings2, ShieldCheck } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { useUndoableAction } from '@/lib/useUndoableAction';

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

const modalOpen = ref(false);
const editingConnectionId = ref(null);
const notices = reactive({
    connection: '',
});
const connectionRemoval = useUndoableAction();

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

const resetConnectionForm = () => {
    editingConnectionId.value = null;
    connectionForm.reset();
    connectionForm.driver = 'openai';
    connectionForm.base_url = 'https://api.openai.com/v1';
    connectionForm.models_text = 'gpt-4.1-mini';
    connectionForm.is_active = true;
    connectionForm.is_default = props.connections.length === 0;
    connectionForm.clearErrors();
};

const closeModal = () => {
    modalOpen.value = false;
    resetConnectionForm();
};

const openCreateModal = () => {
    notices.connection = '';
    modalOpen.value = true;
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
    modalOpen.value = true;
    notices.connection = '';
};

const parseModels = () =>
    `${connectionForm.models_text ?? ''}`
        .split(/[\n,]+/)
        .map((item) => item.trim())
        .filter(Boolean);

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

        closeModal();
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
            <div class="page-lead">
                <h1 class="text-2xl font-semibold tracking-tight">AI Connections</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Provider settings, model availability, and workspace defaults.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="toolbar">
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

                    <div class="toolbar-actions">
                        <button type="button" class="btn-primary" @click="openCreateModal">
                            <Settings2 class="mr-2 h-4 w-4" />
                            New connection
                        </button>
                    </div>
                </div>
            </section>

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

            <section class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Configured connections"
                        description="Use compact connection records instead of per-page AI setup."
                        :icon="Bot"
                    />
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Connection</th>
                            <th>Base URL</th>
                            <th>Models</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="w-[160px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="connection in connections" :key="connection.id">
                            <td>
                                <div class="font-semibold">{{ connection.name }}</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">{{ connection.driver }}</div>
                                <div class="mt-2 inline-meta text-xs">
                                    <span class="inline-meta-item">
                                        <KeyRound />
                                        {{ connection.has_api_key ? 'Key stored' : 'No API key' }}
                                    </span>
                                </div>
                            </td>
                            <td class="mono text-xs">{{ connection.base_url || 'Default API base URL' }}</td>
                            <td class="text-sm text-[var(--muted)]">
                                {{ (connection.models_json ?? []).join(', ') || 'No models listed' }}
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <span class="status-chip">{{ connection.is_active ? 'Active' : 'Inactive' }}</span>
                                    <span v-if="connection.is_default" class="status-chip">Default</span>
                                </div>
                            </td>
                            <td class="text-sm text-[var(--muted)]">{{ connection.updated_at || 'N/A' }}</td>
                            <td>
                                <div class="flex gap-2">
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
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Connection policy"
                    description="This workspace keeps AI settings centralized so operators do not manage providers inside day-to-day screens."
                    :icon="ShieldCheck"
                />

                <div class="role-grid mt-4">
                    <div class="guide-card">
                        <div class="text-block-title">
                            <Server />
                            <span>Provider</span>
                        </div>
                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            PromptLab currently keeps provider configuration OpenAI-compatible and workspace-scoped.
                        </div>
                    </div>
                    <div class="guide-card">
                        <div class="text-block-title">
                            <Link2 />
                            <span>Base URL discipline</span>
                        </div>
                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            Centralize base URLs and model lists here to keep task and experiment screens operational only.
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="modalOpen" max-width="2xl" @close="closeModal">
            <div class="panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="section-title">{{ editingConnectionId ? 'Edit AI connection' : 'Create AI connection' }}</h2>
                        <p class="mt-2 text-sm text-[var(--muted)]">
                            Store model settings once for the current workspace.
                        </p>
                    </div>
                    <button type="button" class="btn-ghost" @click="closeModal">Close</button>
                </div>

                <form class="mt-6 grid gap-4" @submit.prevent="saveConnection">
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
                        <input v-model="connectionForm.api_key" type="password" class="field-input" placeholder="Leave empty to keep the stored key on update">
                    </div>

                    <div>
                        <label class="field-label">Models</label>
                        <textarea v-model="connectionForm.models_text" class="field-textarea" placeholder="gpt-4.1-mini, gpt-4o-mini"></textarea>
                        <div class="field-help">Separate models with commas or line breaks.</div>
                        <div v-if="connectionForm.errors.models_json" class="field-error">{{ connectionForm.errors.models_json }}</div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="check-row">
                            <input v-model="connectionForm.is_active" type="checkbox">
                            <div>
                                <div class="font-semibold">Active</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">Inactive connections stay stored but disappear from selectors.</div>
                            </div>
                        </label>
                        <label class="check-row">
                            <input v-model="connectionForm.is_default" type="checkbox">
                            <div>
                                <div class="font-semibold">Default for workspace</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">Use this connection as the operational default.</div>
                            </div>
                        </label>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="connectionForm.processing">
                            {{ connectionForm.processing ? 'Saving...' : editingConnectionId ? 'Update connection' : 'Create connection' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
