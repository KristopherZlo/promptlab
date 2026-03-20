<script setup>
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterDropdown from '@/Components/FilterDropdown.vue';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, FileStack, Filter, FolderKanban, Plus, UserRound } from 'lucide-vue-next';
import { applyServerErrors, extractServerMessage } from '@/lib/forms';
import { formatDateTime } from '@/lib/formatters';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { routeWithQuery, useUrlState } from '@/lib/urlState';

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

const search = ref(props.filters.search ?? '');
const selectedUseCaseId = ref(null);
const uiState = reactive({
    notice: '',
    createdTask: null,
});
let searchTimer = null;

const statusCounts = computed(() => ({
    active: props.useCases.filter((item) => item.status === 'active').length,
    draft: props.useCases.filter((item) => item.status === 'draft').length,
    withBestPrompt: props.useCases.filter((item) => item.best_prompt).length,
}));

const selectedUseCase = computed(() =>
    props.useCases.find((item) => item.id === selectedUseCaseId.value) ?? props.useCases[0] ?? null,
);
const selectedTemplateCreateHref = computed(() =>
    routeWithQuery(
        'prompt-templates.create',
        {},
        selectedUseCase.value?.id ? { use_case_id: selectedUseCase.value.id } : {},
    ),
);
const selectedUseCaseRunHref = computed(() =>
    routeWithQuery(
        'playground',
        {},
        selectedUseCase.value?.id ? { mode: 'single', use_case_id: selectedUseCase.value.id } : {},
    ),
);

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Draft', value: 'draft' },
    { label: 'Archived', value: 'archived' },
];

const statusLabel = computed(() =>
    statusOptions.find((option) => option.value === props.filters.status)?.label ?? '',
);

watch(
    () => props.useCases,
    (items) => {
        if (!items.length) {
            selectedUseCaseId.value = null;
            return;
        }

        if (!items.some((item) => item.id === selectedUseCaseId.value)) {
            selectedUseCaseId.value = items[0].id;
        }
    },
    { immediate: true },
);

watch(
    () => props.filters.search,
    (value) => {
        const nextValue = value ?? '';

        if (search.value !== nextValue) {
            search.value = nextValue;
        }
    },
);

const applyFilters = (nextFilters) => {
    router.get(route('use-cases.index'), nextFilters, {
        preserveState: true,
        replace: true,
        only: ['useCases', 'filters', 'canManage'],
    });
};

const cleanedFilters = (overrides = {}) =>
    Object.fromEntries(
        Object.entries({
            ...props.filters,
            search: search.value,
            ...overrides,
        })
            .map(([key, value]) => [key, `${value ?? ''}`.trim()])
            .filter(([, value]) => value !== ''),
    );

const updateStatusFilter = (value) => {
    applyFilters(cleanedFilters({ status: value }));
};

watch(search, (value) => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        applyFilters(cleanedFilters({ search: value }));
    }, 240);
});

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer);
});

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
        uiState.notice = 'Task created in the current workspace.';
        uiState.createdTask = {
            id: response.data.data.id,
            name: response.data.data.name,
        };
        activeTab.value = 'directory';
        router.reload({ only: ['useCases'] });
    } catch (error) {
        applyServerErrors(createForm, error);
        uiState.notice = extractServerMessage(error, 'Task could not be created.');
    } finally {
        createForm.processing = false;
    }
};

const tabs = computed(() => {
    const items = [
        { id: 'directory', label: 'Directory', icon: FolderKanban },
    ];

    if (props.canManage) {
        items.push({ id: 'create', label: 'Add task', icon: Plus });
    }

    return items;
});
const activeTab = useUrlState({
    key: 'tab',
    defaultValue: 'directory',
    allowedValues: tabs.value.map((item) => item.id),
});
</script>

<template>
    <Head title="Tasks" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Tasks</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Separate the task directory from task creation so the main working view stays focused on navigation and review.
                </p>
            </div>
        </template>

        <div class="page-frame">
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
                <section v-if="activeTab === 'directory'" class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Task directory</h2>
                            <p class="text-sm text-[var(--muted)]">Browse tasks on the left and inspect the selected task on the right.</p>
                        </div>
                        <div class="console-page-actions">
                            <Link v-if="canManage" :href="selectedTemplateCreateHref" class="btn-secondary">Add prompt</Link>
                        </div>
                    </div>

                    <div v-if="uiState.notice" class="surface-block-body pb-0">
                        <div class="notice-banner">
                            <span>{{ uiState.notice }}</span>
                            <Link
                                v-if="uiState.createdTask"
                                :href="route('use-cases.show', uiState.createdTask.id)"
                                class="ml-2 font-semibold text-[var(--accent)] hover:underline"
                            >
                                Open {{ uiState.createdTask.name }}
                            </Link>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-6">
                        <div class="summary-strip">
                            <div class="summary-item">
                                <div class="summary-item-label">Total tasks</div>
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

                        <div class="surface-muted">
                            <SearchFilterBar
                                :model-value="search"
                                placeholder="Search tasks by name or description..."
                                @update:model-value="search = $event"
                            >
                                <FilterDropdown
                                    label="Status"
                                    :icon="Filter"
                                    :options="statusOptions"
                                    :selected="filters.status || ''"
                                    :selected-label="statusLabel"
                                    width="220px"
                                    @select="updateStatusFilter($event)"
                                    @clear="updateStatusFilter('')"
                                />
                            </SearchFilterBar>
                        </div>

                        <div class="console-page">
                            <div class="console-page-grid">
                                <div class="console-list-pane">
                                    <div class="console-list-head">Tasks</div>

                                    <div v-if="useCases.length" class="console-list-scroll">
                                        <button
                                            v-for="item in useCases"
                                            :key="item.id"
                                            type="button"
                                            class="console-list-item"
                                            :class="{ 'console-list-item-active': item.id === selectedUseCase?.id }"
                                            @click="selectedUseCaseId = item.id"
                                        >
                                            <div class="console-list-title-row">
                                                <div class="console-list-title">{{ item.name }}</div>
                                                <span class="status-chip">{{ item.status }}</span>
                                            </div>

                                            <div class="console-list-meta">{{ item.description || 'No description yet.' }}</div>

                                            <div class="console-list-foot">
                                                <span class="console-list-foot-item">
                                                    <FileStack class="h-3.5 w-3.5" />
                                                    {{ item.prompt_templates_count }} templates
                                                </span>
                                                <span class="console-list-foot-item">
                                                    <FolderKanban class="h-3.5 w-3.5" />
                                                    {{ item.test_cases_count }} tests
                                                </span>
                                            </div>
                                        </button>
                                    </div>

                                    <div v-else class="console-empty-pane">
                                        No tasks match the current filters.
                                    </div>
                                </div>

                                <div class="console-detail-pane">
                                    <template v-if="selectedUseCase">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="max-w-3xl">
                                                <div class="text-sm text-[var(--muted)]">Selected task</div>
                                                <h3 class="mt-2 text-2xl font-semibold tracking-tight text-[var(--ink)]">{{ selectedUseCase.name }}</h3>
                                                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                                    {{ selectedUseCase.description || 'No task description has been added yet.' }}
                                                </p>
                                            </div>

                                            <div class="console-page-actions">
                                                <Link :href="route('use-cases.show', selectedUseCase.id)" class="btn-primary">View task</Link>
                                                <Link v-if="canManage" :href="selectedUseCaseRunHref" class="btn-secondary">Start test</Link>
                                            </div>
                                        </div>

                                        <div class="console-detail-section">
                                            <div class="key-value-grid">
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Prompt templates</div>
                                                    <div class="key-value-value">{{ selectedUseCase.prompt_templates_count }}</div>
                                                </div>
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Saved tests</div>
                                                    <div class="key-value-value">{{ selectedUseCase.test_cases_count }}</div>
                                                </div>
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Created by</div>
                                                    <div class="key-value-value">{{ selectedUseCase.created_by || 'Unknown owner' }}</div>
                                                </div>
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Created at</div>
                                                    <div class="key-value-value">{{ selectedUseCase.created_at ? formatDateTime(selectedUseCase.created_at) : 'No date' }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="console-detail-section">
                                            <div class="console-field-label">Best prompt</div>
                                            <div class="surface-muted mt-3">
                                                <template v-if="selectedUseCase.best_prompt">
                                                    <div class="font-semibold text-[var(--ink)]">
                                                        {{ selectedUseCase.best_prompt.name }} {{ selectedUseCase.best_prompt.version_label }}
                                                    </div>
                                                    <div class="mt-1 text-sm text-[var(--muted)]">
                                                        {{ selectedUseCase.best_prompt.average_score?.toFixed(1) }} average score
                                                    </div>
                                                </template>
                                                <span v-else>No evaluated prompt yet.</span>
                                            </div>
                                        </div>

                                        <div class="console-detail-section">
                                            <div class="console-field-label">Activity</div>
                                            <div class="surface-muted mt-3">
                                                <div class="inline-meta">
                                                    <span class="inline-meta-item">
                                                        <UserRound />
                                                        {{ selectedUseCase.created_by || 'Unknown owner' }}
                                                    </span>
                                                    <span class="inline-meta-item">
                                                        <CalendarClock />
                                                        {{ selectedUseCase.created_at ? formatDateTime(selectedUseCase.created_at) : 'No date' }}
                                                    </span>
                                                    <span v-if="selectedUseCase.updated_by" class="inline-meta-item">
                                                        <UserRound />
                                                        Updated by {{ selectedUseCase.updated_by }}
                                                    </span>
                                                    <span v-if="selectedUseCase.updated_at" class="inline-meta-item">
                                                        <CalendarClock />
                                                        Updated {{ formatDateTime(selectedUseCase.updated_at) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <div v-else class="console-empty-pane">
                                        No tasks match the current filters.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Add task</h2>
                            <p class="text-sm text-[var(--muted)]">Add a new business task without mixing the form into the main directory.</p>
                        </div>
                    </div>

                    <div class="surface-block-body">
                        <div v-if="uiState.notice" class="notice-banner mb-6">{{ uiState.notice }}</div>

                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
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
                                    <button type="button" class="btn-secondary" @click="activeTab = 'directory'">Back to list</button>
                                    <button class="btn-primary" :disabled="createForm.processing">
                                        {{ createForm.processing ? 'Saving...' : 'Add task' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
