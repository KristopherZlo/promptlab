<script setup>
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import RoleBadge from '@/Components/RoleBadge.vue';
import UndoBanner from '@/Components/UndoBanner.vue';
import { ShieldCheck, UserPlus, Users } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage, formatAbilityLabel, formatRoleLabel } from '@/lib/forms';
import { useUndoableAction } from '@/lib/useUndoableAction';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    memberships: {
        type: Array,
        required: true,
    },
    roleOptions: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const inviteOpen = ref(false);
const notices = reactive({
    member: '',
});
const memberRemoval = useUndoableAction();

const memberForm = useForm({
    email: '',
    role: props.roleOptions.includes('editor') ? 'editor' : props.roleOptions[0],
});

const roleDescriptions = {
    owner: 'Full workspace control, including access, AI settings, and approvals.',
    admin: 'Can manage operations and administration inside the current workspace.',
    editor: 'Can work with tasks, prompts, cases, and experiments.',
    reviewer: 'Can review runs and save evaluations without changing setup.',
    viewer: 'Read-only visibility into the workspace.',
};

const currentTeamRole = computed(() => page.props.auth?.current_team?.team_role || '');
const currentPlatformRole = computed(() => page.props.auth?.user?.platform_role || '');
const roleSummary = computed(() =>
    props.roleOptions.map((role) => ({
        role,
        count: props.memberships.filter((membership) => membership.team_role === role).length,
    })),
);

const closeInviteModal = () => {
    inviteOpen.value = false;
    memberForm.reset();
    memberForm.role = props.roleOptions.includes('editor') ? 'editor' : props.roleOptions[0];
    memberForm.clearErrors();
};

const openInviteModal = () => {
    notices.member = '';
    inviteOpen.value = true;
};

const addMember = async () => {
    memberForm.processing = true;
    notices.member = '';

    try {
        await axios.post(route('api.team-memberships.store'), {
            email: memberForm.email,
            role: memberForm.role,
        });

        closeInviteModal();
        notices.member = 'Member added to the current workspace.';
        router.reload({ only: ['memberships', 'team'] });
    } catch (error) {
        applyServerErrors(memberForm, error);
        notices.member = extractServerMessage(error, 'Member could not be added.');
    } finally {
        memberForm.processing = false;
    }
};

const updateMemberRole = async (membership, role) => {
    notices.member = '';

    try {
        await axios.put(route('api.team-memberships.update', membership.id), {
            role,
        });

        notices.member = `${membership.user.display_name || membership.user.name} is now ${formatRoleLabel(role)}.`;
        router.reload({ only: ['memberships', 'team'] });
    } catch (error) {
        notices.member = extractServerMessage(error, 'Role update failed.');
        router.reload({ only: ['memberships', 'team'] });
    }
};

const removeMember = async (membership) => {
    notices.member = '';

    try {
        await axios.delete(route('api.team-memberships.destroy', membership.id));
        notices.member = `${membership.user.display_name || membership.user.name} was removed from the workspace.`;
        router.reload({ only: ['memberships', 'team'] });
    } catch (error) {
        notices.member = extractServerMessage(error, 'Member removal failed.');
    }
};

const scheduleMemberRemoval = (membership) => {
    memberRemoval.scheduleAction({
        label: `${membership.user.display_name || membership.user.name} will be removed from this workspace.`,
        onCommit: () => removeMember(membership),
    });
};
</script>

<template>
    <Head title="Users & Access" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-lead">
                <h1 class="text-2xl font-semibold tracking-tight">Users &amp; Access</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Two-level access management for {{ team.name }}.
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
                            <div class="summary-item-label">Members</div>
                            <div class="summary-item-value">{{ memberships.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Your platform role</div>
                            <div class="summary-item-value">{{ currentPlatformRole === 'admin' ? 'Platform Admin' : 'Platform User' }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Your workspace role</div>
                            <div class="summary-item-value">{{ formatRoleLabel(currentTeamRole) }}</div>
                        </div>
                    </div>

                    <div class="toolbar-actions">
                        <button type="button" class="btn-primary" @click="openInviteModal">
                            <UserPlus class="mr-2 h-4 w-4" />
                            Add member
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="notices.member" class="notice-banner">
                {{ notices.member }}
            </div>

            <UndoBanner
                v-if="memberRemoval.pendingAction.active"
                :label="memberRemoval.pendingAction.label"
                :seconds-remaining="memberRemoval.pendingAction.secondsRemaining"
                :busy="memberRemoval.pendingAction.busy"
                @undo="memberRemoval.cancelAction"
                @commit="memberRemoval.commitAction"
            />

            <section class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Workspace members"
                        description="Each employee has a platform role and a workspace role. Effective permissions are derived from both."
                        :icon="Users"
                    />
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Platform role</th>
                            <th>Workspace role</th>
                            <th>Effective permissions</th>
                            <th class="w-[120px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="membership in memberships" :key="membership.id">
                            <td>
                                <div class="font-semibold">{{ membership.user.display_name || membership.user.name }}</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">{{ membership.user.email }}</div>
                            </td>
                            <td>
                                <RoleBadge :value="membership.user.platform_role" tone="platform" />
                            </td>
                            <td>
                                <select
                                    class="field-select max-w-[180px]"
                                    :value="membership.team_role"
                                    @change="updateMemberRole(membership, $event.target.value)"
                                >
                                    <option v-for="role in roleOptions" :key="role" :value="role">
                                        {{ formatRoleLabel(role) }}
                                    </option>
                                </select>
                            </td>
                            <td class="text-sm text-[var(--muted)]">
                                {{ membership.abilities.map(formatAbilityLabel).join(', ') }}
                            </td>
                            <td>
                                <button type="button" class="btn-ghost text-[var(--danger)]" @click="scheduleMemberRemoval(membership)">
                                    Remove
                                </button>
                            </td>
                        </tr>
                        <tr v-if="memberships.length === 0">
                            <td colspan="5" class="text-[var(--muted)]">No members found in this workspace.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Role ladder"
                    description="Workspace hierarchy stays explicit and separate from platform-wide access."
                    :icon="ShieldCheck"
                />

                <div class="role-grid mt-4">
                    <div v-for="item in roleSummary" :key="item.role" class="guide-card">
                        <div class="flex items-center justify-between gap-3">
                            <RoleBadge :value="item.role" tone="team" />
                            <span class="status-chip">{{ item.count }}</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-[var(--muted)]">
                            {{ roleDescriptions[item.role] }}
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="inviteOpen" max-width="xl" @close="closeInviteModal">
            <div class="panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="section-title">Add workspace member</h2>
                        <p class="mt-2 text-sm text-[var(--muted)]">
                            Invite an existing PromptLab user and assign a workspace role immediately.
                        </p>
                    </div>
                    <button type="button" class="btn-ghost" @click="closeInviteModal">Close</button>
                </div>

                <form class="mt-6 grid gap-4" @submit.prevent="addMember">
                    <div>
                        <label class="field-label">Work email</label>
                        <input v-model="memberForm.email" type="email" class="field-input" placeholder="colleague@company.com">
                        <div v-if="memberForm.errors.email" class="field-error">{{ memberForm.errors.email }}</div>
                    </div>
                    <div>
                        <label class="field-label">Workspace role</label>
                        <select v-model="memberForm.role" class="field-select">
                            <option v-for="role in roleOptions" :key="role" :value="role">
                                {{ formatRoleLabel(role) }}
                            </option>
                        </select>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" @click="closeInviteModal">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="memberForm.processing">
                            {{ memberForm.processing ? 'Adding...' : 'Add member' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
