<script setup>
import axios from 'axios';
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

const activeTab = ref('directory');
const switchingWorkspaceId = ref(null);
const notices = reactive({
    workspace: '',
});

const workspaceForm = useForm({
    name: '',
    description: '',
});

const createWorkspace = async () => {
    workspaceForm.processing = true;
    notices.workspace = '';

    try {
        const response = await axios.post(route('api.teams.store'), {
            name: workspaceForm.name,
            description: workspaceForm.description || null,
        });

        workspaceForm.reset();
        activeTab.value = 'directory';
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
            <div>
                <h1>Workspaces</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Separate the current workspace view, workspace directory, and workspace creation into distinct screens.
                </p>
            </div>
        </template>

        <div class="page-frame">
            <aside class="page-frame-rail">
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'directory' }"
                    @click="activeTab = 'directory'"
                >
                    <Building2 class="h-4 w-4 shrink-0" />
                    <span>Directory</span>
                </button>
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'current' }"
                    @click="activeTab = 'current'"
                >
                    <Shield class="h-4 w-4 shrink-0" />
                    <span>Current</span>
                </button>
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'create' }"
                    @click="activeTab = 'create'"
                >
                    <Plus class="h-4 w-4 shrink-0" />
                    <span>Create</span>
                </button>
            </aside>

            <div class="page-frame-content">
                <section class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Workspace snapshot</h2>
                            <p class="text-sm text-[var(--muted)]">Visible totals for the active workspace and the accessible workspace set.</p>
                        </div>
                    </div>

                    <div class="surface-block-body">
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
                    </div>
                </section>

                <div v-if="notices.workspace" class="notice-banner">
                    {{ notices.workspace }}
                </div>

                <section v-if="activeTab === 'current'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Current workspace</h2>
                            <p class="text-sm text-[var(--muted)]">Identity and ownership details for the workspace you are managing right now.</p>
                        </div>
                    </div>

                    <div class="surface-block-body grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                        <div class="surface-muted">
                            <div class="font-semibold">{{ currentWorkspace.name }}</div>
                            <div class="mt-2 mono text-xs text-[var(--muted)]">{{ currentWorkspace.slug }}</div>
                            <div class="mt-4 text-sm leading-6 text-[var(--muted)]">
                                {{ currentWorkspace.description || 'No description has been added for this workspace.' }}
                            </div>
                        </div>

                        <div class="surface-muted">
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

                <section v-else-if="activeTab === 'create'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Create workspace</h2>
                            <p class="text-sm text-[var(--muted)]">Create a separate workspace for another business stream or team.</p>
                        </div>
                    </div>

                    <div class="surface-block-body">
                        <form class="grid gap-4" @submit.prevent="createWorkspace">
                            <div>
                                <label class="field-label">Workspace name</label>
                                <input v-model="workspaceForm.name" type="text" class="field-input" placeholder="Operations AI Team">
                                <div v-if="workspaceForm.errors.name" class="field-error">{{ workspaceForm.errors.name }}</div>
                            </div>
                            <div>
                                <label class="field-label">Description</label>
                                <textarea v-model="workspaceForm.description" class="field-textarea" placeholder="Describe scope and ownership."></textarea>
                            </div>

                            <div class="flex flex-wrap gap-3 border-t border-[var(--line)] pt-4">
                                <button type="button" class="btn-secondary" @click="activeTab = 'directory'">Back to directory</button>
                                <button type="submit" class="btn-primary" :disabled="workspaceForm.processing">
                                    {{ workspaceForm.processing ? 'Creating...' : 'Create workspace' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section v-else class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Accessible workspaces</h2>
                            <p class="text-sm text-[var(--muted)]">Switch operating context without leaving the administrative area.</p>
                        </div>
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
        </div>
    </AuthenticatedLayout>
</template>
