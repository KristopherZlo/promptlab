<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import UndoBanner from '@/Components/UndoBanner.vue';
import { Download, UserPlus } from 'lucide-vue-next';
import {
    applyServerErrors,
    extractServerMessage,
    formatAbilityLabel,
    formatPlatformRoleLabel,
    formatRoleLabel,
} from '@/lib/forms';
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
const activeTab = ref('members');
const search = ref('');
const selectedMembershipId = ref(null);
const notices = reactive({
    member: '',
});
const memberRemoval = useUndoableAction();

const tabs = [
    { id: 'members', label: 'Members' },
    { id: 'roles', label: 'Roles' },
];

const roleDescriptions = {
    owner: 'Full workspace control, including access, AI settings, and approvals.',
    admin: 'Can manage operations and administration inside the current workspace.',
    editor: 'Can work with tasks, prompts, cases, and experiments.',
    reviewer: 'Can review runs and save evaluations without changing setup.',
    viewer: 'Read-only visibility into the workspace.',
};

const memberForm = useForm({
    email: '',
    role: props.roleOptions.includes('editor') ? 'editor' : props.roleOptions[0],
});

const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const roleSummary = computed(() =>
    props.roleOptions.map((role) => ({
        role,
        count: props.memberships.filter((membership) => membership.team_role === role).length,
    })),
);

const sortedMemberships = computed(() => {
    const roleOrder = props.roleOptions.reduce((map, role, index) => {
        map[role] = index;
        return map;
    }, {});

    return [...props.memberships].sort((left, right) => {
        const leftIsCurrent = left.user.id === currentUserId.value ? 1 : 0;
        const rightIsCurrent = right.user.id === currentUserId.value ? 1 : 0;

        if (leftIsCurrent !== rightIsCurrent) {
            return rightIsCurrent - leftIsCurrent;
        }

        const leftRoleOrder = roleOrder[left.team_role] ?? Number.MAX_SAFE_INTEGER;
        const rightRoleOrder = roleOrder[right.team_role] ?? Number.MAX_SAFE_INTEGER;

        if (leftRoleOrder !== rightRoleOrder) {
            return leftRoleOrder - rightRoleOrder;
        }

        return memberName(left).localeCompare(memberName(right));
    });
});

const filteredMemberships = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return sortedMemberships.value;
    }

    return sortedMemberships.value.filter((membership) => {
        const haystack = [
            memberName(membership),
            membership.user.email,
            membership.team_role,
            membership.user.platform_role,
            membership.abilities.map(formatAbilityLabel).join(' '),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const selectedMembership = computed(() =>
    filteredMemberships.value.find((membership) => membership.id === selectedMembershipId.value)
    ?? null,
);

const detailAbilityLabels = computed(() => selectedMembership.value?.abilities?.map(formatAbilityLabel) ?? []);

watch(filteredMemberships, (items) => {
    if (!items.length) {
        selectedMembershipId.value = null;
        return;
    }

    if (selectedMembershipId.value && !items.some((item) => item.id === selectedMembershipId.value)) {
        selectedMembershipId.value = null;
    }
}, { immediate: true });

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

const memberName = (membership) =>
    membership?.user?.display_name
    || membership?.user?.name
    || 'Unknown member';

const memberInitials = (membership) =>
    memberName(membership)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
    || 'U';

const isCurrentUser = (membership) => membership?.user?.id === currentUserId.value;

const selectMembership = (membershipId) => {
    selectedMembershipId.value = membershipId;
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
        await axios.put(route('api.team-memberships.update', membership.id), { role });
        notices.member = `${memberName(membership)} is now ${formatRoleLabel(role)}.`;
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
        notices.member = `${memberName(membership)} was removed from the workspace.`;
        router.reload({ only: ['memberships', 'team'] });
    } catch (error) {
        notices.member = extractServerMessage(error, 'Member removal failed.');
    }
};

const scheduleMemberRemoval = (membership) => {
    memberRemoval.scheduleAction({
        label: `${memberName(membership)} will be removed from this workspace.`,
        onCommit: () => removeMember(membership),
    });
};

const escapeCsvValue = (value) => {
    const normalized = `${value ?? ''}`.replace(/"/g, '""');
    return `"${normalized}"`;
};

const exportMembers = () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const rows = [
        ['Name', 'Email', 'Workspace Role', 'Platform Role', 'Permissions'],
        ...filteredMemberships.value.map((membership) => [
            memberName(membership),
            membership.user.email,
            formatRoleLabel(membership.team_role),
            formatPlatformRoleLabel(membership.user.platform_role),
            membership.abilities.map(formatAbilityLabel).join(', '),
        ]),
    ];

    const csv = rows
        .map((row) => row.map((value) => escapeCsvValue(value)).join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `${(props.team.slug || props.team.name || 'members').toLowerCase().replace(/[^a-z0-9]+/g, '-')}-members.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="People & Permissions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-lead">
                <h1 class="text-2xl font-semibold tracking-tight">People &amp; Permissions</h1>
            </div>
        </template>

        <div class="people-access-page">
            <div class="page-tabs people-access-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>

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

            <section v-if="activeTab === 'members'" class="people-access-shell">
                <div class="people-access-toolbar">
                    <div class="people-access-toolbar-search">
                        <SearchFilterBar
                            :model-value="search"
                            placeholder="Search members..."
                            @update:model-value="search = $event"
                        />
                    </div>

                    <div class="people-access-toolbar-actions">
                        <button type="button" class="btn-secondary people-access-action" @click="exportMembers">
                            <Download class="h-4 w-4" />
                            <span>Export</span>
                        </button>
                        <button type="button" class="btn-primary people-access-action" @click="openInviteModal">
                            <UserPlus class="h-4 w-4" />
                            <span>Add member</span>
                        </button>
                    </div>
                </div>

                <div class="people-access-grid">
                    <div class="people-access-list">
                        <div
                            v-for="membership in filteredMemberships"
                            :key="membership.id"
                            class="people-access-member"
                            :class="{ 'people-access-member-active': membership.id === selectedMembership?.id }"
                        >
                            <button
                                type="button"
                                class="people-access-member-main people-access-member-hit"
                                @click="selectMembership(membership.id)"
                            >
                                <div class="people-access-avatar">{{ memberInitials(membership) }}</div>

                                <div class="people-access-member-copy">
                                    <div class="people-access-member-name-row">
                                        <div class="people-access-member-name">{{ memberName(membership) }}</div>
                                        <span v-if="isCurrentUser(membership)" class="people-access-tag people-access-tag-self">You</span>
                                        <span class="people-access-tag">{{ formatRoleLabel(membership.team_role) }}</span>
                                    </div>

                                    <div class="people-access-member-email">{{ membership.user.email }}</div>
                                </div>
                            </button>

                            <div class="people-access-member-actions" @click.stop>
                                <button
                                    type="button"
                                    class="btn-secondary people-access-mini"
                                    @click.stop="scheduleMemberRemoval(membership)"
                                >
                                    {{ isCurrentUser(membership) ? 'Leave' : 'Remove' }}
                                </button>
                                <button
                                    type="button"
                                    class="btn-primary people-access-mini"
                                    @click.stop="selectMembership(membership.id)"
                                >
                                    Roles
                                </button>
                            </div>
                        </div>

                        <div v-if="filteredMemberships.length === 0" class="people-access-empty">
                            No members match the current search.
                        </div>
                    </div>

                    <div class="people-access-detail">
                        <template v-if="selectedMembership">
                            <div class="people-access-detail-header">
                                <div class="people-access-avatar people-access-avatar-large">
                                    {{ memberInitials(selectedMembership) }}
                                </div>

                                <div class="min-w-0">
                                    <h2 class="people-access-detail-name">{{ memberName(selectedMembership) }}</h2>
                                    <div class="people-access-detail-email">{{ selectedMembership.user.email }}</div>
                                </div>
                            </div>

                            <div class="people-access-detail-tags">
                                <span v-if="isCurrentUser(selectedMembership)" class="people-access-tag people-access-tag-self">You</span>
                                <span class="people-access-tag">{{ formatRoleLabel(selectedMembership.team_role) }}</span>
                                <span class="people-access-tag">{{ formatPlatformRoleLabel(selectedMembership.user.platform_role) }}</span>
                            </div>

                            <div class="people-access-detail-section">
                                <label class="field-label">Workspace role</label>
                                <select
                                    class="field-select people-access-role-select"
                                    :value="selectedMembership.team_role"
                                    @change="updateMemberRole(selectedMembership, $event.target.value)"
                                >
                                    <option v-for="role in roleOptions" :key="role" :value="role">
                                        {{ formatRoleLabel(role) }}
                                    </option>
                                </select>
                            </div>

                            <div class="people-access-detail-section">
                                <div class="people-access-detail-label">Effective permissions</div>
                                <div class="people-access-permissions">
                                    <span v-for="ability in detailAbilityLabels" :key="ability" class="people-access-permission">
                                        {{ ability }}
                                    </span>
                                    <div v-if="detailAbilityLabels.length === 0" class="people-access-muted">
                                        No explicit permissions available.
                                    </div>
                                </div>
                            </div>

                            <div class="people-access-detail-section">
                                <div class="people-access-detail-label">Workspace access</div>
                                <p class="people-access-copy">
                                    Platform access stays separate from workspace membership. Changing the role here only affects what this member can do inside {{ team.name }}.
                                </p>
                            </div>

                            <div class="people-access-detail-footer">
                                <button
                                    type="button"
                                    class="btn-ghost text-[var(--danger)]"
                                    @click="scheduleMemberRemoval(selectedMembership)"
                                >
                                    {{ isCurrentUser(selectedMembership) ? 'Leave workspace' : 'Remove member' }}
                                </button>
                            </div>
                        </template>

                        <div v-else class="people-access-detail-empty">
                            Select a member to view details.
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="people-access-roles-panel">
                <div class="people-access-roles-list">
                    <div v-for="item in roleSummary" :key="item.role" class="people-access-role-row">
                        <div>
                            <div class="people-access-role-name">{{ formatRoleLabel(item.role) }}</div>
                            <div class="people-access-role-copy">{{ roleDescriptions[item.role] }}</div>
                        </div>
                        <div class="people-access-role-count">{{ item.count }} {{ item.count === 1 ? 'member' : 'members' }}</div>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="inviteOpen" max-width="xl" @close="closeInviteModal">
            <div class="panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="section-title">Add member</h2>
                        <p class="mt-2 text-sm text-[var(--muted)]">
                            Invite an existing user and assign a workspace role immediately.
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
