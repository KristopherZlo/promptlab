<script setup>
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import UndoBanner from '@/Components/UndoBanner.vue';
import { applyServerErrors, extractServerMessage, formatRoleLabel } from '@/lib/forms';
import { formatDateTime } from '@/lib/formatters';
import { useUndoableAction } from '@/lib/useUndoableAction';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Bot, Clock3, History, KeyRound, Link2, Server, Settings2, Shield, Trash2, Users, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    roleOptions: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const abilities = computed(() => page.props.auth.abilities ?? []);

const canManageTeam = computed(() => abilities.value.includes('manage_team'));
const canManageMembers = computed(() => abilities.value.includes('manage_members'));
const canManageConnections = computed(() => abilities.value.includes('manage_connections'));
const canViewAudit = computed(() => abilities.value.includes('view_audit'));

const teamForm = useForm({
    name: '',
    description: '',
});

const memberForm = useForm({
    email: '',
    role: props.roleOptions.includes('editor') ? 'editor' : props.roleOptions[0],
});

const connectionForm = useForm({
    id: null,
    name: '',
    driver: 'openai',
    base_url: 'https://api.openai.com/v1',
    api_key: '',
    models_text: 'gpt-5.2',
    is_active: true,
    is_default: props.team.connections.length === 0,
});

const notices = reactive({
    team: '',
    member: '',
    connection: '',
});

const editingConnectionId = ref(null);
const activeTab = ref('members');
const memberRemoval = useUndoableAction();
const connectionRemoval = useUndoableAction();
const tabs = computed(() => {
    const items = [
        { id: 'members', label: 'Members', icon: Users },
        { id: 'roles', label: 'Access Levels', icon: Shield },
    ];

    if (canManageConnections.value) {
        items.push({ id: 'connections', label: 'AI connections', icon: Bot });
    }

    if (canViewAudit.value) {
        items.push({ id: 'audit', label: 'Audit', icon: History });
    }

    items.push({ id: 'team', label: 'Workspace', icon: Settings2 });

    return items;
});

const roleDescriptions = {
    owner: 'Full control over the team, people, AI connections, and approvals.',
    admin: 'Administrative control inside the team without being the original owner.',
    editor: 'Can create and update tasks, prompts, test cases, and run experiments.',
    reviewer: 'Can run experiments and save evaluations, but cannot change workspace setup.',
    viewer: 'Read-only access to the workspace.',
};

const parseModels = () =>
    `${connectionForm.models_text ?? ''}`
        .split(/[\n,]+/)
        .map((item) => item.trim())
        .filter(Boolean);

const resetConnectionForm = () => {
    editingConnectionId.value = null;
    connectionForm.reset();
    connectionForm.base_url = 'https://api.openai.com/v1';
    connectionForm.driver = 'openai';
    connectionForm.models_text = 'gpt-5.2';
    connectionForm.is_active = true;
    connectionForm.is_default = props.team.connections.length === 0;
    connectionForm.clearErrors();
};

const editConnection = (connection) => {
    editingConnectionId.value = connection.id;
    connectionForm.id = connection.id;
    connectionForm.name = connection.name;
    connectionForm.driver = connection.driver;
    connectionForm.base_url = connection.base_url || 'https://api.openai.com/v1';
    connectionForm.api_key = '';
    connectionForm.models_text = (connection.models_json ?? []).join(', ');
    connectionForm.is_active = connection.is_active;
    connectionForm.is_default = connection.is_default;
    connectionForm.clearErrors();
    notices.connection = '';
};

const createTeam = async () => {
    teamForm.processing = true;
    notices.team = '';

    try {
        const response = await axios.post(route('api.teams.store'), {
            name: teamForm.name,
            description: teamForm.description || null,
        });

        teamForm.reset();
        router.visit(response.data.redirect_url);
    } catch (error) {
        applyServerErrors(teamForm, error);
        notices.team = extractServerMessage(error, 'Team could not be created.');
    } finally {
        teamForm.processing = false;
    }
};

const addMember = async () => {
    memberForm.processing = true;
    notices.member = '';

    try {
        await axios.post(route('api.team-memberships.store'), {
            email: memberForm.email,
            role: memberForm.role,
        });

        memberForm.reset();
        memberForm.role = props.roleOptions.includes('editor') ? 'editor' : props.roleOptions[0];
        notices.member = 'Member added to the current team.';
        router.reload({ only: ['team'] });
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
        router.reload({ only: ['team'] });
    } catch (error) {
        notices.member = extractServerMessage(error, 'Role update failed.');
        router.reload({ only: ['team'] });
    }
};

const removeMember = async (membership) => {
    notices.member = '';

    try {
        await axios.delete(route('api.team-memberships.destroy', membership.id));
        notices.member = `${membership.user.display_name || membership.user.name} was removed from the team.`;
        router.reload({ only: ['team'] });
    } catch (error) {
        notices.member = extractServerMessage(error, 'Member removal failed.');
    }
};

const saveConnection = async () => {
    connectionForm.processing = true;
    notices.connection = '';

    try {
        const payload = {
            name: connectionForm.name,
            driver: connectionForm.driver,
            base_url: connectionForm.base_url || null,
            api_key: connectionForm.api_key || null,
            models_json: parseModels(),
            is_active: !!connectionForm.is_active,
            is_default: !!connectionForm.is_default,
        };

        if (editingConnectionId.value) {
            await axios.put(route('api.llm-connections.update', editingConnectionId.value), payload);
            notices.connection = 'Connection updated.';
        } else {
            await axios.post(route('api.llm-connections.store'), payload);
            notices.connection = 'Connection created.';
        }

        resetConnectionForm();
        router.reload({ only: ['team'] });
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
        router.reload({ only: ['team'] });
    } catch (error) {
        notices.connection = extractServerMessage(error, 'Connection removal failed.');
    }
};

const scheduleMemberRemoval = (membership) => {
    memberRemoval.scheduleAction({
        label: `${membership.user.display_name || membership.user.name} will be removed from this team.`,
        onCommit: () => removeMember(membership),
    });
};

const scheduleConnectionRemoval = (connection) => {
    connectionRemoval.scheduleAction({
        label: `${connection.name} will be deleted from this team workspace.`,
        onCommit: () => removeConnection(connection),
    });
};
</script>

<template>
    <Head title="Team Workspace" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Team Workspace</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Manage access, AI connections, and audit history for <span class="font-bold text-[var(--ink)]">{{ team.name }}</span>.
                </p>
            </div>
        </template>

        <div class="page-frame">
            <ToastRelay :message="notices.member" />
            <ToastRelay :message="notices.connection" />
            <ToastRelay :message="notices.team" />

            <div class="page-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="page-tab"
                    :class="{ 'page-tab-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                    <span>{{ tab.label }}</span>
                </button>
            </div>

            <div class="page-frame-content">
            <section class="panel p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Team</div>
                            <div class="summary-item-value">{{ team.name }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Members</div>
                            <div class="summary-item-value">{{ team.memberships.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Connections</div>
                            <div class="summary-item-value">{{ team.connections.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Audit entries</div>
                            <div class="summary-item-value">{{ team.activity_logs.length }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Link :href="route('dashboard')" class="btn-secondary">Back to dashboard</Link>
                        <Link :href="route('playground')" class="btn-primary">Start test</Link>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'team'" class="panel p-5">
                <PanelHeader
                    title="Current team"
                    description="Core information about the active workspace."
                    :icon="Shield"
                />

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="guide-card">
                        <div class="text-block-title">
                            <Shield />
                            <span>Team name</span>
                        </div>
                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ team.name }}</div>
                    </div>
                    <div class="guide-card">
                        <div class="text-block-title">
                            <Link2 />
                            <span>Workspace slug</span>
                        </div>
                        <div class="mt-2 mono text-sm">{{ team.slug }}</div>
                    </div>
                    <div class="guide-card">
                        <div class="text-block-title">
                            <Users />
                            <span>Members</span>
                        </div>
                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ team.memberships.length }} people in this team</div>
                    </div>
                </div>

                <div v-if="team.description" class="mt-4">
                    <div class="field-label">Description</div>
                    <div class="guide-card mt-2 text-sm leading-6">{{ team.description }}</div>
                </div>
            </section>

            <section v-if="activeTab === 'members'" class="panel p-5">
                <PanelHeader
                    title="Team members"
                    description="Add people, adjust their role, or remove access."
                    :icon="Users"
                />

                <UndoBanner
                    v-if="memberRemoval.pendingAction.active"
                    class="mt-4"
                    :label="memberRemoval.pendingAction.label"
                    :seconds-remaining="memberRemoval.pendingAction.secondsRemaining"
                    :busy="memberRemoval.pendingAction.busy"
                    @undo="memberRemoval.cancelAction"
                    @commit="memberRemoval.commitAction"
                />

                <div class="mt-4 overflow-hidden rounded-[10px] border border-[var(--line)]">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th v-if="canManageMembers">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="membership in team.memberships" :key="membership.id">
                                <td class="font-bold">{{ membership.user.display_name || membership.user.name }}</td>
                                <td>{{ membership.user.email }}</td>
                                <td>
                                    <template v-if="canManageMembers">
                                        <select
                                            class="field-select max-w-[160px]"
                                            :value="membership.role"
                                            @change="updateMemberRole(membership, $event.target.value)"
                                        >
                                            <option v-for="role in roleOptions" :key="role" :value="role">
                                                {{ formatRoleLabel(role) }}
                                            </option>
                                        </select>
                                    </template>
                                    <template v-else>
                                        {{ formatRoleLabel(membership.role) }}
                                    </template>
                                </td>
                                <td v-if="canManageMembers">
                                    <button
                                        type="button"
                                        class="btn-danger btn-icon-only"
                                        :title="`Remove ${membership.user.display_name || membership.user.name}`"
                                        :aria-label="`Remove ${membership.user.display_name || membership.user.name}`"
                                        @click="scheduleMemberRemoval(membership)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    v-if="canManageMembers"
                    class="mt-5 grid gap-4 md:grid-cols-[1.1fr_0.8fr_auto]"
                    @submit.prevent="addMember"
                >
                    <div>
                        <label class="field-label">Find existing user by work email</label>
                        <input v-model="memberForm.email" type="email" class="field-input" placeholder="colleague@company.com">
                        <div v-if="memberForm.errors.email" class="field-error">{{ memberForm.errors.email }}</div>
                    </div>
                    <div>
                        <label class="field-label">Role</label>
                        <select v-model="memberForm.role" class="field-select">
                            <option v-for="role in roleOptions" :key="role" :value="role">
                                {{ formatRoleLabel(role) }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary w-full" :disabled="memberForm.processing">
                            {{ memberForm.processing ? 'Adding...' : 'Add person' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="activeTab === 'roles'" class="panel p-5">
                <PanelHeader
                    title="Team roles"
                    description="Roles separate administration from prompt authoring and review."
                    :icon="Users"
                />

                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="role in roleOptions" :key="role" class="guide-card">
                        <div class="text-block-title">
                            <Shield />
                            <span>{{ formatRoleLabel(role) }}</span>
                        </div>
                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            {{ roleDescriptions[role] || 'Custom team role.' }}
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeTab === 'connections'" class="panel p-5">
                <PanelHeader
                    title="Real AI connections"
                    description="Store OpenAI-compatible API settings once for this team. Connected models appear in Prompt Templates and Playground."
                    :icon="Bot"
                />

                <UndoBanner
                    v-if="connectionRemoval.pendingAction.active"
                    class="mt-4"
                    :label="connectionRemoval.pendingAction.label"
                    :seconds-remaining="connectionRemoval.pendingAction.secondsRemaining"
                    :busy="connectionRemoval.pendingAction.busy"
                    @undo="connectionRemoval.cancelAction"
                    @commit="connectionRemoval.commitAction"
                />

                <div class="mt-4 space-y-3">
                    <div v-for="connection in team.connections" :key="connection.id" class="guide-card">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold">{{ connection.name }}</div>
                                <div class="mt-2 inline-meta">
                                    <span class="inline-meta-item">
                                        <Server />
                                        {{ connection.driver }}
                                    </span>
                                    <span class="inline-meta-item">
                                        <Link2 />
                                        {{ connection.base_url || 'Default API base URL' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="connection.is_default" class="status-chip">Default</span>
                                <span class="status-chip">{{ connection.is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                        </div>
                        <div class="mt-3 inline-meta">
                            <span class="inline-meta-item">
                                <Bot />
                                Models: {{ (connection.models_json ?? []).join(', ') || 'No models listed' }}
                            </span>
                        </div>
                        <div class="mt-2 inline-meta">
                            <span class="inline-meta-item">
                                <KeyRound />
                                API key: {{ connection.has_api_key ? 'Stored' : 'Missing' }}
                            </span>
                        </div>
                        <div v-if="canManageConnections" class="mt-4 flex gap-3">
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
                    </div>
                </div>

                <form v-if="canManageConnections" class="mt-5 space-y-4" @submit.prevent="saveConnection">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <div class="label-with-icon">
                                <Bot />
                                <span>Connection name</span>
                            </div>
                            <input v-model="connectionForm.name" type="text" class="field-input" placeholder="OpenAI Production">
                            <div v-if="connectionForm.errors.name" class="field-error">{{ connectionForm.errors.name }}</div>
                        </div>
                        <div>
                            <div class="label-with-icon">
                                <Link2 />
                                <span>Base URL</span>
                            </div>
                            <input v-model="connectionForm.base_url" type="url" class="field-input" placeholder="https://api.openai.com/v1">
                        </div>
                    </div>

                    <div>
                        <div class="label-with-icon">
                            <KeyRound />
                            <span>API key</span>
                        </div>
                        <input v-model="connectionForm.api_key" type="password" class="field-input" placeholder="Leave empty to keep the stored key on update">
                        <div class="field-help">Stored encrypted at rest and never returned in API responses after save.</div>
                    </div>

                    <div>
                        <div class="label-with-icon">
                            <Server />
                            <span>Models</span>
                        </div>
                        <textarea v-model="connectionForm.models_text" class="field-textarea" placeholder="gpt-5.2, gpt-5-mini"></textarea>
                        <div class="field-help">Enter one or more model names separated by commas or line breaks.</div>
                        <div v-if="connectionForm.errors.models_json" class="field-error">{{ connectionForm.errors.models_json }}</div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="check-row">
                            <input v-model="connectionForm.is_active" type="checkbox">
                            <div>
                                <div class="font-bold">Active</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">Inactive connections stay stored but disappear from model selectors.</div>
                            </div>
                        </label>
                        <label class="check-row">
                            <input v-model="connectionForm.is_default" type="checkbox">
                            <div>
                                <div class="font-bold">Default for team</div>
                                <div class="mt-1 text-sm text-[var(--muted)]">Use this as the main connection for the team.</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary" :disabled="connectionForm.processing">
                            {{ connectionForm.processing ? 'Saving...' : editingConnectionId ? 'Save changes' : 'Add connection' }}
                        </button>
                        <button type="button" class="btn-danger" @click="resetConnectionForm">
                            <X class="h-4 w-4" />
                            <span>Clear</span>
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="canViewAudit && activeTab === 'audit'" class="panel p-5">
                <PanelHeader
                    title="Audit trail"
                    description="See who changed what and when inside this team."
                    :icon="History"
                />

                <div class="mt-4 space-y-3">
                    <div v-for="entry in team.activity_logs" :key="entry.id" class="guide-card">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold">{{ entry.action }}</div>
                                <div class="mt-2 inline-meta">
                                    <span class="inline-meta-item">
                                        <Users />
                                        {{ entry.actor || 'System' }}
                                    </span>
                                    <span class="inline-meta-item">
                                        <Clock3 />
                                        {{ formatDateTime(entry.created_at) }}
                                    </span>
                                </div>
                            </div>
                            <div class="inline-meta-item text-sm text-[var(--muted)]">
                                <Shield />
                                <span>{{ entry.subject_label || 'Workspace event' }}</span>
                            </div>
                        </div>
                        <div v-if="Object.keys(entry.details_json ?? {}).length" class="mt-3 text-sm leading-6 text-[var(--muted)]">
                            {{ JSON.stringify(entry.details_json) }}
                        </div>
                    </div>

                    <div v-if="team.activity_logs.length === 0" class="empty-state">
                        No audit entries yet.
                    </div>
                </div>
            </section>

            <section v-if="canManageTeam && activeTab === 'team'" class="panel p-5">
                <PanelHeader
                    title="Create another team"
                    description="Create a separate workspace when a different business unit or experiment stream should not share prompts and access."
                    :icon="Settings2"
                />

                <form class="mt-5 space-y-4" @submit.prevent="createTeam">
                    <div>
                        <label class="field-label">Team name</label>
                        <input v-model="teamForm.name" type="text" class="field-input" placeholder="Operations AI Team">
                        <div v-if="teamForm.errors.name" class="field-error">{{ teamForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="field-label">Description</label>
                        <textarea v-model="teamForm.description" class="field-textarea" placeholder="Explain what this team owns and why it needs a separate workspace."></textarea>
                    </div>
                    <button type="submit" class="btn-primary" :disabled="teamForm.processing">
                        {{ teamForm.processing ? 'Adding...' : 'Add workspace' }}
                    </button>
                </form>
            </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
