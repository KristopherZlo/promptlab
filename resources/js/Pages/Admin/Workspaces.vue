<script setup>
import axios from 'axios';
import { reactive } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RoleBadge from '@/Components/RoleBadge.vue';
import { Plus, Shield } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { routeWithQuery, useUrlState } from '@/lib/urlState';

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

const activeTab = useUrlState({
    key: 'tab',
    defaultValue: 'current',
    allowedValues: ['current', 'create'],
});
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
        await axios.post(route('api.teams.store'), {
            name: workspaceForm.name,
            description: workspaceForm.description || null,
        });

        workspaceForm.reset();
        activeTab.value = 'current';
        router.visit(routeWithQuery('admin.workspaces', {}, { tab: 'current' }));
    } catch (error) {
        applyServerErrors(workspaceForm, error);
        notices.workspace = extractServerMessage(error, 'Workspace could not be created.');
    } finally {
        workspaceForm.processing = false;
    }
};
</script>

<template>
    <Head title="Workspace Setup" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Workspace Setup</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Manage the current workspace here. Use the left sidebar when you need to switch to a different one.
                </p>
            </div>
        </template>

        <div class="page-frame">
            <div class="page-tabs">
                <button
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === 'current' }"
                    @click="activeTab = 'current'"
                >
                    <Shield class="h-4 w-4 shrink-0" />
                    <span>Current</span>
                </button>
                <button
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === 'create' }"
                    @click="activeTab = 'create'"
                >
                    <Plus class="h-4 w-4 shrink-0" />
                    <span>Add workspace</span>
                </button>
            </div>

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
                                <div class="summary-item-label">Available in sidebar</div>
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

                    <div class="surface-block-body space-y-4">
                        <div class="surface-muted">
                            <div class="text-sm text-[var(--muted)]">
                                Switching between workspaces now happens only in the left sidebar. This page is kept for setup and creation, not for changing context.
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
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
                    </div>
                </section>

                <section v-else class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Add workspace</h2>
                            <p class="text-sm text-[var(--muted)]">Add a separate workspace for another business stream or team.</p>
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
                                <button type="button" class="btn-secondary" @click="activeTab = 'current'">Back to current workspace</button>
                                <button type="submit" class="btn-primary" :disabled="workspaceForm.processing">
                                    {{ workspaceForm.processing ? 'Adding...' : 'Add workspace' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
