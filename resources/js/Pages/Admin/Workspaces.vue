<script setup>
import axios from 'axios';
import { reactive, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import RoleBadge from '@/Components/RoleBadge.vue';
import { Building2, Plus, RefreshCcw, Shield } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';

const props = defineProps({
    currentWorkspace: {
        type: Object,
        required: true,
    },
    workspaces: {
        type: Array,
        required: true,
    },
});

const workspaceModalOpen = ref(false);
const switchingWorkspaceId = ref(null);
const notices = reactive({
    workspace: '',
});

const workspaceForm = useForm({
    name: '',
    description: '',
});

const openWorkspaceModal = () => {
    notices.workspace = '';
    workspaceModalOpen.value = true;
};

const closeWorkspaceModal = () => {
    workspaceModalOpen.value = false;
    workspaceForm.reset();
    workspaceForm.clearErrors();
};

const createWorkspace = async () => {
    workspaceForm.processing = true;
    notices.workspace = '';

    try {
        const response = await axios.post(route('api.teams.store'), {
            name: workspaceForm.name,
            description: workspaceForm.description || null,
        });

        closeWorkspaceModal();
        router.visit(response.data.redirect_url);
    } catch (error) {
        applyServerErrors(workspaceForm, error);
        notices.workspace = extractServerMessage(error, 'Workspace could not be created.');
    } finally {
        workspaceForm.processing = false;
    }
};

const switchWorkspace = async (workspace) => {
    switchingWorkspaceId.value = workspace.id;
    notices.workspace = '';

    try {
        await axios.post(route('api.teams.switch'), { team_id: workspace.id });
        router.visit(route('admin.workspaces'));
    } catch (error) {
        notices.workspace = extractServerMessage(error, 'Workspace switch failed.');
    } finally {
        switchingWorkspaceId.value = null;
    }
};
</script>

<template>
    <Head title="Workspaces" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-lead">
                <h1 class="text-2xl font-semibold tracking-tight">Workspaces</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Active workspace settings, ownership, and available workspaces.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="toolbar">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Current workspace</div>
                            <div class="summary-item-value">{{ currentWorkspace.name }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Visible workspaces</div>
                            <div class="summary-item-value">{{ workspaces.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Members</div>
                            <div class="summary-item-value">{{ currentWorkspace.members_count }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Owner</div>
                            <div class="summary-item-value">{{ currentWorkspace.creator || 'Unknown' }}</div>
                        </div>
                    </div>

                    <div class="toolbar-actions">
                        <button type="button" class="btn-primary" @click="openWorkspaceModal">
                            <Plus class="mr-2 h-4 w-4" />
                            Create workspace
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="notices.workspace" class="notice-banner">
                {{ notices.workspace }}
            </div>

            <section class="panel p-5">
                <PanelHeader
                    title="Current workspace"
                    description="Operational summary of the workspace you are managing right now."
                    :icon="Shield"
                />

                <div class="mt-4 grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                    <div class="guide-card">
                        <div class="font-semibold">{{ currentWorkspace.name }}</div>
                        <div class="mt-2 mono text-xs text-[var(--muted)]">{{ currentWorkspace.slug }}</div>
                        <div class="mt-4 text-sm leading-6 text-[var(--muted)]">
                            {{ currentWorkspace.description || 'No description has been added for this workspace.' }}
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="summary-list">
                            <div class="summary-row">
                                <span>Owner</span>
                                <span class="font-semibold">{{ currentWorkspace.creator || 'Unknown' }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Members</span>
                                <span class="font-semibold">{{ currentWorkspace.members_count }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Status</span>
                                <RoleBadge value="owner" tone="team" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Accessible workspaces"
                        description="Switch context without leaving the administrative area."
                        :icon="Building2"
                    />
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Owner</th>
                            <th>Members</th>
                            <th>Status</th>
                            <th class="w-[140px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="workspace in workspaces" :key="workspace.id">
                            <td>
                                <div class="font-semibold">{{ workspace.name }}</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">{{ workspace.description || 'No description' }}</div>
                            </td>
                            <td>{{ workspace.creator || 'Unknown' }}</td>
                            <td>{{ workspace.members_count }}</td>
                            <td>
                                <span v-if="workspace.is_current" class="status-chip">Current</span>
                                <span v-else class="status-chip">Available</span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-secondary"
                                    :disabled="workspace.is_current || switchingWorkspaceId === workspace.id"
                                    @click="switchWorkspace(workspace)"
                                >
                                    <RefreshCcw class="mr-2 h-4 w-4" />
                                    {{ workspace.is_current ? 'Selected' : switchingWorkspaceId === workspace.id ? 'Switching...' : 'Switch' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <Modal :show="workspaceModalOpen" max-width="xl" @close="closeWorkspaceModal">
            <div class="panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="section-title">Create workspace</h2>
                        <p class="mt-2 text-sm text-[var(--muted)]">
                            Create a separate workspace for another business stream or team.
                        </p>
                    </div>
                    <button type="button" class="btn-ghost" @click="closeWorkspaceModal">Close</button>
                </div>

                <form class="mt-6 grid gap-4" @submit.prevent="createWorkspace">
                    <div>
                        <label class="field-label">Workspace name</label>
                        <input v-model="workspaceForm.name" type="text" class="field-input" placeholder="Operations AI Team">
                        <div v-if="workspaceForm.errors.name" class="field-error">{{ workspaceForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="field-label">Description</label>
                        <textarea v-model="workspaceForm.description" class="field-textarea" placeholder="Describe scope and ownership."></textarea>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" @click="closeWorkspaceModal">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="workspaceForm.processing">
                            {{ workspaceForm.processing ? 'Creating...' : 'Create workspace' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
