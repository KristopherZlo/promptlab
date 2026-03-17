<script setup>
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    FileStack,
    FlaskConical,
    FolderKanban,
    LayoutDashboard,
    Settings2,
    UserRound,
} from 'lucide-vue-next';
import { reactive } from 'vue';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { formatDateTime } from '@/lib/formatters';
import { useUrlState } from '@/lib/urlState';

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

const tabs = [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard },
    { id: 'templates', label: 'Templates', icon: FileStack },
    { id: 'experiments', label: 'Experiments', icon: FlaskConical },
    { id: 'test-cases', label: 'Test cases', icon: FolderKanban },
    { id: 'settings', label: 'Settings', icon: Settings2, manageOnly: true },
];
const activeTab = useUrlState({
    key: 'tab',
    defaultValue: 'overview',
    allowedValues: tabs
        .filter((item) => !item.manageOnly || props.canManage)
        .map((item) => item.id),
});
</script>

<template>
    <Head :title="useCase.name" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>{{ useCase.name }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    One task, split into separate views for overview, prompt work, experiments, reusable cases, and configuration.
                </p>
                <div class="mt-3 inline-meta">
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

        <div class="page-frame">
            <aside class="page-frame-rail">
                <button
                    v-for="tab in tabs.filter((item) => !item.manageOnly || canManage)"
                    :key="tab.id"
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                    <span>{{ tab.label }}</span>
                </button>
            </aside>

            <div class="page-frame-content">
                <section class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Task status</h2>
                            <p class="text-sm text-[var(--muted)]">Keep high-level maturity signals in one stable area while the detailed work happens in tabs.</p>
                        </div>
                        <div class="console-page-actions">
                            <Link
                                v-if="canManage && activeTab !== 'templates'"
                                :href="route('prompt-templates.create')"
                                class="btn-secondary"
                            >
                                New prompt template
                            </Link>
                            <Link :href="route('playground')" class="btn-primary">Open experiments</Link>
                        </div>
                    </div>

                    <div class="surface-block-body">
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
                    </div>
                </section>

                <section v-if="activeTab === 'overview'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Overview</h2>
                            <p class="text-sm text-[var(--muted)]">Reference information that defines the task and its current best reusable prompt.</p>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-4">
                        <div class="key-value-grid">
                            <div class="key-value-item">
                                <div class="key-value-label">Status</div>
                                <div class="key-value-value capitalize">{{ useCase.status }}</div>
                            </div>
                            <div class="key-value-item">
                                <div class="key-value-label">Input label</div>
                                <div class="key-value-value">{{ useCase.primary_input_label || 'Not set' }}</div>
                            </div>
                            <div class="key-value-item">
                                <div class="key-value-label">Slug</div>
                                <div class="key-value-value mono text-sm">{{ useCase.slug || 'Auto-generated' }}</div>
                            </div>
                            <div class="key-value-item">
                                <div class="key-value-label">Business goal</div>
                                <div class="key-value-value">{{ useCase.business_goal || 'No business goal recorded yet.' }}</div>
                            </div>
                        </div>

                        <div class="surface-muted">
                            <div class="console-field-label">Best prompt</div>
                            <div class="mt-3">
                                <template v-if="useCase.best_prompt">
                                    <div class="font-semibold text-[var(--ink)]">
                                        {{ useCase.best_prompt.name }} {{ useCase.best_prompt.version_label }}
                                    </div>
                                    <div class="mt-1 text-sm text-[var(--muted)]">
                                        {{ useCase.best_prompt.average_score?.toFixed(1) }} average score
                                    </div>
                                </template>
                                <span v-else>No evaluated prompt yet.</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'templates'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Prompt templates</h2>
                            <p class="text-sm text-[var(--muted)]">Keep all prompt families for this task in their own view.</p>
                        </div>
                        <Link v-if="canManage" :href="route('prompt-templates.create')" class="btn-primary">New template</Link>
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

                <section v-else-if="activeTab === 'experiments'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Recent experiments</h2>
                            <p class="text-sm text-[var(--muted)]">Review execution history separately from task definition.</p>
                        </div>
                    </div>

                    <div class="record-list">
                        <div v-for="experiment in recentExperiments" :key="experiment.id" class="record-list-item">
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
                        <div v-if="recentExperiments.length === 0" class="record-list-item text-sm text-[var(--muted)]">
                            No recent experiments for this task.
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'test-cases'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Saved test cases</h2>
                            <p class="text-sm text-[var(--muted)]">Keep reusable inputs and the form for adding new cases in their own layer.</p>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-6">
                        <div class="surface-muted">
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
                        </div>

                        <div v-if="canManage" class="surface-muted">
                            <div class="section-title">Add test case</div>
                            <p class="mt-2 text-sm text-[var(--muted)]">Save a new reusable example for future compare or batch runs.</p>

                            <div v-if="uiState.testCaseNotice" class="notice-banner mt-4">
                                {{ uiState.testCaseNotice }}
                            </div>

                            <form class="mt-5 grid gap-4" @submit.prevent="createTestCase">
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
                    </div>
                </section>

                <section v-else class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Settings</h2>
                            <p class="text-sm text-[var(--muted)]">Update the stable task definition separately from prompt and experiment review.</p>
                        </div>
                        <button class="btn-primary" :disabled="editForm.processing" @click="saveUseCase">
                            {{ editForm.processing ? 'Saving...' : 'Save task' }}
                        </button>
                    </div>

                    <div class="surface-block-body">
                        <div v-if="uiState.useCaseNotice" class="notice-banner mb-5">
                            {{ uiState.useCaseNotice }}
                        </div>

                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveUseCase">
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
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
