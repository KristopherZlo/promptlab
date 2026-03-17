<script setup>
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, FolderKanban, ListFilter, Plus, UserRound } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { formatDateTime } from '@/lib/formatters';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    useCases: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    canManage: {
        type: Boolean,
        required: true,
    },
});

const createForm = useForm({
    name: '',
    slug: '',
    description: '',
    business_goal: '',
    primary_input_label: '',
    status: 'active',
});

const showCreateModal = ref(false);
const uiState = reactive({
    notice: '',
    createdTask: null,
});

const statusCounts = computed(() => ({
    active: props.useCases.filter((item) => item.status === 'active').length,
    draft: props.useCases.filter((item) => item.status === 'draft').length,
    withBestPrompt: props.useCases.filter((item) => item.best_prompt).length,
}));

const filterBy = (key, value) => {
    router.get(route('use-cases.index'), { ...props.filters, [key]: value }, { preserveState: true, replace: true });
};

const submit = async () => {
    createForm.processing = true;
    createForm.clearErrors();
    uiState.notice = '';
    uiState.createdTask = null;

    try {
        const response = await axios.post(route('api.use-cases.store'), {
            name: createForm.name,
            slug: createForm.slug || null,
            description: createForm.description || null,
            business_goal: createForm.business_goal || null,
            primary_input_label: createForm.primary_input_label || null,
            status: createForm.status,
        });

        createForm.reset();
        createForm.status = 'active';
        showCreateModal.value = false;
        uiState.notice = 'Task created in the current team.';
        uiState.createdTask = {
            id: response.data.data.id,
            name: response.data.data.name,
        };
        router.reload({ only: ['useCases'] });
    } catch (error) {
        applyServerErrors(createForm, error);
        uiState.notice = extractServerMessage(error, 'Task could not be created.');
    } finally {
        createForm.processing = false;
    }
};

const openCreateModal = () => {
    uiState.notice = '';
    createForm.clearErrors();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};
</script>

<template>
    <Head title="Tasks" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Tasks</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">Business tasks, saved examples, and linked prompt work.</p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Total</div>
                            <div class="summary-item-value">{{ useCases.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Active</div>
                            <div class="summary-item-value">{{ statusCounts.active }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Draft</div>
                            <div class="summary-item-value">{{ statusCounts.draft }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">With best prompt</div>
                            <div class="summary-item-value">{{ statusCounts.withBestPrompt }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button v-if="canManage" type="button" class="btn-primary" @click="openCreateModal">
                            <Plus class="mr-2 h-4 w-4" />
                            Create task
                        </button>
                        <Link v-if="canManage" :href="route('prompt-templates.create')" class="btn-secondary">New prompt template</Link>
                    </div>
                </div>
            </section>

            <div v-if="uiState.notice" class="notice-banner">
                <span>{{ uiState.notice }}</span>
                <Link
                    v-if="uiState.createdTask"
                    :href="route('use-cases.show', uiState.createdTask.id)"
                    class="ml-2 font-bold text-[var(--accent)] hover:underline"
                >
                    Open {{ uiState.createdTask.name }}
                </Link>
            </div>

            <section class="panel p-4">
                <PanelHeader
                    title="Filter tasks"
                    description="Use filters to narrow the list when the workspace grows."
                    :icon="ListFilter"
                />
                <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <input
                        :value="filters.search || ''"
                        type="text"
                        class="field-input md:max-w-sm"
                        placeholder="Search tasks"
                        @input="filterBy('search', $event.target.value)"
                    >
                    <select
                        class="field-select md:max-w-[180px]"
                        :value="filters.status || ''"
                        @change="filterBy('status', $event.target.value)"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Task list"
                        description="Open a task to manage its prompt templates, saved tests, and review flow."
                        :icon="FolderKanban"
                    />
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Templates</th>
                            <th>Tests</th>
                            <th>Best prompt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in useCases" :key="item.id">
                            <td>
                                <Link :href="route('use-cases.show', item.id)" class="font-bold hover:underline">
                                    {{ item.name }}
                                </Link>
                                <div class="mt-1 text-sm text-[var(--muted)]">{{ item.description }}</div>
                                <div class="mt-2 inline-meta text-xs">
                                    <span class="inline-meta-item">
                                        <UserRound />
                                        {{ item.created_by || 'Unknown owner' }}
                                    </span>
                                    <span class="inline-meta-item">
                                        <CalendarClock />
                                        {{ item.created_at ? formatDateTime(item.created_at) : 'No date' }}
                                    </span>
                                    <span v-if="item.updated_by" class="inline-meta-item">
                                        <UserRound />
                                        Updated by {{ item.updated_by }}
                                    </span>
                                    <span v-if="item.updated_at" class="inline-meta-item">
                                        <CalendarClock />
                                        Updated {{ formatDateTime(item.updated_at) }}
                                    </span>
                                </div>
                            </td>
                            <td>{{ item.prompt_templates_count }}</td>
                            <td>{{ item.test_cases_count }}</td>
                            <td>
                                <div v-if="item.best_prompt" class="text-sm">
                                    <div class="font-bold">{{ item.best_prompt.name }} {{ item.best_prompt.version_label }}</div>
                                    <div class="text-[var(--muted)]">{{ item.best_prompt.average_score?.toFixed(1) }} score</div>
                                </div>
                                <span v-else class="text-sm text-[var(--muted)]">No evaluated prompt yet</span>
                            </td>
                        </tr>
                        <tr v-if="useCases.length === 0">
                            <td colspan="4" class="text-[var(--muted)]">No tasks match the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <Modal :show="showCreateModal" max-width="2xl" @close="closeCreateModal">
            <div class="panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="section-title">Create task</h2>
                        <p class="mt-2 text-sm text-[var(--muted)]">
                            Add a new business task for the team without leaving the list.
                        </p>
                    </div>
                    <button type="button" class="btn-ghost" @click="closeCreateModal">Close</button>
                </div>

                <form class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div v-if="uiState.notice" class="notice-banner md:col-span-2">
                        {{ uiState.notice }}
                    </div>

                    <div>
                        <label class="field-label">Name</label>
                        <input v-model="createForm.name" type="text" class="field-input" autofocus>
                        <div v-if="createForm.errors.name" class="field-error">{{ createForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="field-label">Slug</label>
                        <input v-model="createForm.slug" type="text" class="field-input" placeholder="Optional">
                        <div v-if="createForm.errors.slug" class="field-error">{{ createForm.errors.slug }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="field-label">Description</label>
                        <textarea v-model="createForm.description" class="field-textarea"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="field-label">Business goal</label>
                        <textarea v-model="createForm.business_goal" class="field-textarea"></textarea>
                    </div>
                    <div>
                        <label class="field-label">Primary input label</label>
                        <input v-model="createForm.primary_input_label" type="text" class="field-input" placeholder="Customer message">
                    </div>
                    <div>
                        <label class="field-label">Status</label>
                        <select v-model="createForm.status" class="field-select">
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--line)] pt-4">
                        <div class="text-sm text-[var(--muted)]">
                            The creator and timestamps will be recorded automatically.
                        </div>
                        <div class="flex gap-3">
                            <button type="button" class="btn-secondary" @click="closeCreateModal">Cancel</button>
                            <button class="btn-primary" :disabled="createForm.processing">
                                {{ createForm.processing ? 'Creating...' : 'Create task' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
