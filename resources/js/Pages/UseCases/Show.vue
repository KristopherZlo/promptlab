<script setup>
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, FileStack, FlaskConical, FolderKanban, Settings2, UserRound } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { formatDateTime } from '@/lib/formatters';

const props = defineProps({
    useCase: {
        type: Object,
        required: true,
    },
    detail: {
        type: Object,
        required: true,
    },
    recentExperiments: {
        type: Array,
        required: true,
    },
    canManage: {
        type: Boolean,
        required: true,
    },
});

const editForm = useForm({
    name: props.useCase.name,
    slug: props.useCase.slug,
    description: props.useCase.description || '',
    business_goal: props.useCase.business_goal || '',
    primary_input_label: props.useCase.primary_input_label || '',
    status: props.useCase.status,
});

const testCaseForm = useForm({
    title: '',
    input_text: '',
    expected_output: '',
    expected_json: {},
    variables_json: {},
    metadata_json: {},
    status: 'active',
});

const uiState = reactive({
    useCaseNotice: '',
    testCaseNotice: '',
});
const activeTab = ref('overview');

const saveUseCase = async () => {
    editForm.processing = true;
    uiState.useCaseNotice = '';

    try {
        await axios.put(route('api.use-cases.update', props.useCase.id), {
            name: editForm.name,
            slug: editForm.slug || null,
            description: editForm.description || null,
            business_goal: editForm.business_goal || null,
            primary_input_label: editForm.primary_input_label || null,
            status: editForm.status,
        });

        uiState.useCaseNotice = 'Task saved without leaving the page.';
        editForm.clearErrors();
        router.reload({ only: ['useCase', 'detail'] });
    } catch (error) {
        applyServerErrors(editForm, error);
        uiState.useCaseNotice = extractServerMessage(error, 'Task could not be saved.');
    } finally {
        editForm.processing = false;
    }
};

const createTestCase = async () => {
    testCaseForm.processing = true;
    uiState.testCaseNotice = '';

    try {
        await axios.post(route('api.test-cases.store', props.useCase.id), {
            title: testCaseForm.title,
            input_text: testCaseForm.input_text,
            expected_output: testCaseForm.expected_output || null,
            expected_json: testCaseForm.expected_json,
            variables_json: testCaseForm.variables_json,
            metadata_json: testCaseForm.metadata_json,
            status: testCaseForm.status,
        });

        testCaseForm.reset();
        testCaseForm.status = 'active';
        uiState.testCaseNotice = 'Test case added to this task.';
        router.reload({ only: ['useCase', 'detail'] });
    } catch (error) {
        applyServerErrors(testCaseForm, error);
        uiState.testCaseNotice = extractServerMessage(error, 'Test case could not be created.');
    } finally {
        testCaseForm.processing = false;
    }
};
</script>

<template>
    <Head :title="useCase.name" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ useCase.name }}</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">{{ useCase.description }}</p>
                <div class="mt-2 inline-meta">
                    <span class="inline-meta-item">
                        <UserRound />
                        {{ useCase.created_by || 'Unknown owner' }}
                    </span>
                    <span class="inline-meta-item">
                        <CalendarClock />
                        {{ useCase.created_at ? formatDateTime(useCase.created_at) : 'No date' }}
                    </span>
                    <span v-if="useCase.updated_by" class="inline-meta-item">
                        <UserRound />
                        Updated by {{ useCase.updated_by }}
                    </span>
                    <span v-if="useCase.updated_at" class="inline-meta-item">
                        <CalendarClock />
                        Updated {{ formatDateTime(useCase.updated_at) }}
                    </span>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Prompt templates</div>
                            <div class="summary-item-value">{{ detail.prompt_templates_count }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Test cases</div>
                            <div class="summary-item-value">{{ detail.test_cases_count }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Runs</div>
                            <div class="summary-item-value">{{ detail.runs_count }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Average score</div>
                            <div class="summary-item-value">{{ detail.average_score?.toFixed(1) || 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Link :href="route('prompt-templates.create')" class="btn-secondary">New prompt template</Link>
                        <Link :href="route('playground')" class="btn-primary">Open experiments</Link>
                    </div>
                </div>
            </section>

            <section class="panel p-5">
                <div class="page-tabs">
                    <button type="button" class="page-tab" :class="{ 'page-tab-active': activeTab === 'overview' }" @click="activeTab = 'overview'">
                        Overview
                    </button>
                    <button type="button" class="page-tab" :class="{ 'page-tab-active': activeTab === 'test-cases' }" @click="activeTab = 'test-cases'">
                        Test cases
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="page-tab"
                        :class="{ 'page-tab-active': activeTab === 'settings' }"
                        @click="activeTab = 'settings'"
                    >
                        Settings
                    </button>
                </div>
            </section>

            <div v-if="activeTab === 'overview'" class="space-y-6">
                <section class="panel overflow-hidden">
                    <div class="border-b border-[var(--line)] px-5 py-4">
                        <PanelHeader
                            title="Prompt templates"
                            description="Templates attached to this task."
                            :icon="FileStack"
                        >
                            <template #actions>
                                <Link :href="route('prompt-templates.create')" class="btn-secondary">New template</Link>
                            </template>
                        </PanelHeader>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Task</th>
                                <th>Versions</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="template in useCase.prompt_templates" :key="template.id">
                                <td>
                                    <Link :href="route('prompt-templates.show', template.id)" class="font-bold hover:underline">
                                        {{ template.name }}
                                    </Link>
                                    <div class="mt-1 text-sm text-[var(--muted)]">{{ template.description }}</div>
                                </td>
                                <td class="capitalize">{{ template.task_type }}</td>
                                <td>{{ template.versions_count }}</td>
                                <td>{{ template.average_score?.toFixed(1) || 'N/A' }}</td>
                            </tr>
                            <tr v-if="useCase.prompt_templates.length === 0">
                                <td colspan="4" class="text-[var(--muted)]">No prompt templates attached to this task yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="panel overflow-hidden">
                    <div class="border-b border-[var(--line)] px-5 py-4">
                        <PanelHeader
                            title="Recent experiments"
                            description="Latest runs for this task."
                            :icon="FlaskConical"
                        />
                    </div>
                    <div class="divide-y divide-[var(--line)]">
                        <div v-for="experiment in recentExperiments" :key="experiment.id" class="px-5 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <Link :href="route('experiments.show', experiment.id)" class="font-bold hover:underline">
                                        {{ experiment.mode }} experiment
                                    </Link>
                                    <div class="mt-2 inline-meta text-xs">
                                        <span class="inline-meta-item">
                                            <UserRound />
                                            {{ experiment.created_by || 'Unknown author' }}
                                        </span>
                                        <span class="inline-meta-item">
                                            <CalendarClock />
                                            {{ experiment.created_at ? formatDateTime(experiment.created_at) : 'No date' }}
                                        </span>
                                        <span class="inline-meta-item">
                                            <FlaskConical />
                                            {{ experiment.model_name }}
                                        </span>
                                    </div>
                                </div>
                                <span class="status-chip">{{ experiment.status }}</span>
                            </div>
                        </div>
                        <div v-if="recentExperiments.length === 0" class="px-5 py-5 text-sm text-[var(--muted)]">
                            No recent experiments for this task.
                        </div>
                    </div>
                </section>
            </div>

            <div v-else-if="activeTab === 'test-cases'" class="space-y-6">
                <section class="panel overflow-hidden">
                    <div class="border-b border-[var(--line)] px-5 py-4">
                        <PanelHeader
                            title="Saved test cases"
                            description="Reusable inputs for compare and batch runs."
                            :icon="FolderKanban"
                        />
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Input</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="testCase in useCase.test_cases" :key="testCase.id">
                                <td class="font-bold">{{ testCase.title }}</td>
                                <td class="text-sm text-[var(--muted)]">{{ testCase.input_text }}</td>
                                <td><span class="status-chip">{{ testCase.status }}</span></td>
                            </tr>
                            <tr v-if="useCase.test_cases.length === 0">
                                <td colspan="3" class="text-[var(--muted)]">No saved test cases yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <details v-if="canManage" class="disclosure">
                    <summary class="disclosure-summary">
                        <div>
                            <div class="section-title">Add test case</div>
                            <div class="mt-1 text-sm text-[var(--muted)]">Save a new reusable example for this task.</div>
                        </div>
                        <span class="text-sm font-bold text-[var(--muted)]">Open</span>
                    </summary>

                    <div class="disclosure-content">
                        <div v-if="uiState.testCaseNotice" class="notice-banner mb-4">
                            {{ uiState.testCaseNotice }}
                        </div>
                        <form class="grid gap-4" @submit.prevent="createTestCase">
                            <div>
                                <label class="field-label">Title</label>
                                <input v-model="testCaseForm.title" type="text" class="field-input">
                                <div v-if="testCaseForm.errors.title" class="field-error">{{ testCaseForm.errors.title }}</div>
                            </div>
                            <div>
                                <label class="field-label">Input text</label>
                                <textarea v-model="testCaseForm.input_text" class="field-textarea"></textarea>
                                <div v-if="testCaseForm.errors.input_text" class="field-error">{{ testCaseForm.errors.input_text }}</div>
                            </div>
                            <button class="btn-primary self-start" :disabled="testCaseForm.processing">Create test case</button>
                        </form>
                    </div>
                </details>
            </div>

            <section v-else-if="canManage" class="panel p-5">
                <PanelHeader
                    title="Edit task"
                    description="Update the task definition and status."
                    :icon="Settings2"
                />
                <div v-if="uiState.useCaseNotice" class="notice-banner mt-4">
                    {{ uiState.useCaseNotice }}
                </div>
                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="saveUseCase">
                    <div>
                        <label class="field-label">Name</label>
                        <input v-model="editForm.name" type="text" class="field-input">
                        <div v-if="editForm.errors.name" class="field-error">{{ editForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="field-label">Slug</label>
                        <input v-model="editForm.slug" type="text" class="field-input">
                    </div>
                    <div class="md:col-span-2">
                        <label class="field-label">Description</label>
                        <textarea v-model="editForm.description" class="field-textarea"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="field-label">Business goal</label>
                        <textarea v-model="editForm.business_goal" class="field-textarea"></textarea>
                    </div>
                    <div>
                        <label class="field-label">Input label</label>
                        <input v-model="editForm.primary_input_label" type="text" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Status</label>
                        <select v-model="editForm.status" class="field-select">
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                        <div v-if="editForm.errors.status" class="field-error">{{ editForm.errors.status }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <button class="btn-primary" :disabled="editForm.processing">Save task</button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
