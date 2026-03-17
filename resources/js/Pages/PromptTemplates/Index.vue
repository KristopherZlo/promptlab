<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterDropdown from '@/Components/FilterDropdown.vue';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import { Bot, ClipboardList, Filter, FolderKanban, UserRound } from 'lucide-vue-next';
import { formatDateTime, formatScore } from '@/lib/formatters';
import { routeWithQuery } from '@/lib/urlState';

const props = defineProps({
    templates: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    useCases: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const taskTypes = ['summarization', 'classification', 'rewrite', 'extraction', 'generation'];
const statuses = ['active', 'draft', 'archived'];
const selectedTemplateId = ref(null);

const filterForm = reactive({
    search: props.filters.search ?? '',
    use_case_id: props.filters.use_case_id ?? '',
    task_type: props.filters.task_type ?? '',
    status: props.filters.status ?? '',
    author: props.filters.author ?? '',
    preferred_model: props.filters.preferred_model ?? '',
});

const canManageTemplates = computed(() => (page.props.auth?.abilities ?? []).includes('manage_prompts'));
const taskTypeOptions = taskTypes.map((taskType) => ({
    label: taskType,
    value: taskType,
}));
const statusOptions = statuses.map((status) => ({
    label: status,
    value: status,
}));
const useCaseLabel = computed(() =>
    props.useCases.find((useCase) => `${useCase.id}` === `${filterForm.use_case_id}`)?.name ?? '',
);
const taskTypeLabel = computed(() =>
    taskTypeOptions.find((item) => item.value === filterForm.task_type)?.label ?? '',
);
const statusLabel = computed(() =>
    statusOptions.find((item) => item.value === filterForm.status)?.label ?? '',
);

const preferredModels = computed(() =>
    [...new Set(
        props.templates
            .flatMap((template) => [
                template.preferred_model,
                ...(template.versions ?? []).map((version) => version.preferred_model),
            ])
            .filter(Boolean),
    )].sort((left, right) => left.localeCompare(right)),
);

const hasFilters = computed(() =>
    Object.values(filterForm).some((value) => `${value ?? ''}`.trim() !== ''),
);

const selectedTemplate = computed(() =>
    props.templates.find((template) => template.id === selectedTemplateId.value) ?? props.templates[0] ?? null,
);
const selectedTemplateRunHref = computed(() => {
    if (!selectedTemplate.value) {
        return route('playground');
    }

    const latestVersionId = selectedTemplate.value.versions?.at(-1)?.id ?? '';

    return routeWithQuery('playground', {}, {
        mode: 'single',
        use_case_id: selectedTemplate.value.use_case_id,
        prompt_template_id: selectedTemplate.value.id,
        prompt_version_id: latestVersionId,
    });
});

const statusBreakdown = computed(() =>
    statuses.map((status) => ({
        status,
        count: props.templates.filter((template) => template.status === status).length,
    })),
);

watch(
    () => props.templates,
    (items) => {
        if (!items.length) {
            selectedTemplateId.value = null;
            return;
        }

        if (!items.some((item) => item.id === selectedTemplateId.value)) {
            selectedTemplateId.value = items[0].id;
        }
    },
    { immediate: true },
);

const cleanedFilters = () =>
    Object.fromEntries(
        Object.entries(filterForm)
            .map(([key, value]) => [key, `${value ?? ''}`.trim()])
            .filter(([, value]) => value !== ''),
    );

let searchTimer = null;
let authorTimer = null;
let suspendTextRequests = false;

const pushFilters = () => {
    router.get(route('prompt-templates.index'), cleanedFilters(), {
        preserveState: true,
        replace: true,
        only: ['templates', 'filters', 'useCases'],
    });
};

const updateFilter = (key, value) => {
    filterForm[key] = value;

    pushFilters();
};

const resetFilters = () => {
    window.clearTimeout(searchTimer);
    window.clearTimeout(authorTimer);

    suspendTextRequests = true;
    Object.assign(filterForm, {
        search: '',
        use_case_id: '',
        task_type: '',
        status: '',
        author: '',
        preferred_model: '',
    });
    suspendTextRequests = false;

    router.get(route('prompt-templates.index'), {}, {
        preserveState: true,
        replace: true,
        only: ['templates', 'filters', 'useCases'],
    });
};

watch(
    () => props.filters.search,
    (value) => {
        const nextValue = value ?? '';

        if (filterForm.search !== nextValue) {
            suspendTextRequests = true;
            filterForm.search = nextValue;
            suspendTextRequests = false;
        }
    },
);

watch(
    () => props.filters.author,
    (value) => {
        const nextValue = value ?? '';

        if (filterForm.author !== nextValue) {
            suspendTextRequests = true;
            filterForm.author = nextValue;
            suspendTextRequests = false;
        }
    },
);

watch(
    () => filterForm.search,
    () => {
        if (suspendTextRequests) {
            return;
        }

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            pushFilters();
        }, 240);
    },
);

watch(
    () => filterForm.author,
    () => {
        if (suspendTextRequests) {
            return;
        }

        window.clearTimeout(authorTimer);
        authorTimer = window.setTimeout(() => {
            pushFilters();
        }, 240);
    },
);

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer);
    window.clearTimeout(authorTimer);
});
</script>

<template>
    <Head title="Prompt Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Prompt Templates</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Search and narrow the template catalog from a single working view.
                </p>
            </div>
        </template>

        <div class="page-frame-content">
            <section class="surface-block">
                    <div class="surface-block-header">
                        <div>
                            <h2 class="section-title">Template directory</h2>
                            <p class="text-sm text-[var(--muted)]">Browse families on the left and inspect the selected template on the right.</p>
                        </div>
                        <div class="console-page-actions">
                            <Link v-if="canManageTemplates" :href="route('prompt-templates.create')" class="btn-primary">New template</Link>
                        </div>
                    </div>

                    <div class="surface-block-body space-y-6">
                        <div class="summary-strip">
                            <div class="summary-item">
                                <div class="summary-item-label">Templates</div>
                                <div class="summary-item-value">{{ templates.length }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Models seen</div>
                                <div class="summary-item-value">{{ preferredModels.length }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Active</div>
                                <div class="summary-item-value">{{ statusBreakdown.find((row) => row.status === 'active')?.count ?? 0 }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-item-label">Draft</div>
                                <div class="summary-item-value">{{ statusBreakdown.find((row) => row.status === 'draft')?.count ?? 0 }}</div>
                            </div>
                        </div>

                        <SearchFilterBar
                            :model-value="filterForm.search"
                            placeholder="Search templates by name or description..."
                            @update:model-value="filterForm.search = $event"
                        >
                            <FilterDropdown
                                label="Task"
                                :icon="FolderKanban"
                                :options="useCases.map((useCase) => ({ label: useCase.name, value: useCase.id }))"
                                :selected="filterForm.use_case_id"
                                :selected-label="useCaseLabel"
                                width="260px"
                                @select="updateFilter('use_case_id', $event)"
                                @clear="updateFilter('use_case_id', '')"
                            />
                            <FilterDropdown
                                label="Task type"
                                :icon="ClipboardList"
                                :options="taskTypeOptions"
                                :selected="filterForm.task_type"
                                :selected-label="taskTypeLabel"
                                width="220px"
                                @select="updateFilter('task_type', $event)"
                                @clear="updateFilter('task_type', '')"
                            />
                            <FilterDropdown
                                label="Status"
                                :icon="Filter"
                                :options="statusOptions"
                                :selected="filterForm.status"
                                :selected-label="statusLabel"
                                width="220px"
                                @select="updateFilter('status', $event)"
                                @clear="updateFilter('status', '')"
                            />
                            <FilterDropdown
                                label="Model"
                                :icon="Bot"
                                :options="preferredModels.map((model) => ({ label: model, value: model }))"
                                :selected="filterForm.preferred_model"
                                :selected-label="filterForm.preferred_model"
                                width="260px"
                                @select="updateFilter('preferred_model', $event)"
                                @clear="updateFilter('preferred_model', '')"
                            />
                            <FilterDropdown
                                label="Author"
                                :icon="UserRound"
                                :selected="filterForm.author"
                                :selected-label="filterForm.author"
                                width="260px"
                                @clear="updateFilter('author', '')"
                            >
                                <template #default="{ close }">
                                    <div class="filter-menu-panel">
                                        <input
                                            :value="filterForm.author"
                                            type="text"
                                            class="filter-menu-field"
                                            placeholder="Author name"
                                            @input="filterForm.author = $event.target.value"
                                        >
                                    </div>
                                    <button
                                        type="button"
                                        class="filter-menu-clear"
                                        @click="
                                            filterForm.author = '';
                                            close();
                                        "
                                    >
                                        Clear
                                    </button>
                                </template>
                            </FilterDropdown>
                            <button v-if="hasFilters" type="button" class="filter-toolbar-reset" @click="resetFilters">
                                Reset
                            </button>
                        </SearchFilterBar>

                        <div class="console-page">
                            <div class="console-page-grid">
                                <div class="console-list-pane">
                                    <div class="console-list-head">Templates</div>

                                    <div v-if="templates.length" class="console-list-scroll">
                                        <button
                                            v-for="template in templates"
                                            :key="template.id"
                                            type="button"
                                            class="console-list-item"
                                            :class="{ 'console-list-item-active': template.id === selectedTemplate?.id }"
                                            @click="selectedTemplateId = template.id"
                                        >
                                            <div class="console-list-title-row">
                                                <div class="console-list-title">{{ template.name }}</div>
                                                <span class="status-chip">{{ template.status }}</span>
                                            </div>

                                            <div class="console-list-meta">
                                                {{ template.use_case?.name || 'No task assigned' }} | {{ template.task_type }}
                                            </div>

                                            <div class="console-list-foot">
                                                <span class="console-list-foot-item">{{ template.versions_count }} versions</span>
                                                <span class="console-list-foot-item">{{ formatScore(template.average_score) }}</span>
                                            </div>
                                        </button>
                                    </div>

                                    <div v-else class="console-empty-pane">
                                        No prompt templates match the current filters.
                                    </div>
                                </div>

                                <div class="console-detail-pane">
                                    <template v-if="selectedTemplate">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="max-w-3xl">
                                                <div class="text-sm text-[var(--muted)]">Selected template</div>
                                                <h3 class="mt-2 text-2xl font-semibold tracking-tight text-[var(--ink)]">{{ selectedTemplate.name }}</h3>
                                                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                                    {{ selectedTemplate.description || 'No template description has been added yet.' }}
                                                </p>
                                            </div>

                                            <div class="console-page-actions">
                                                <Link :href="route('prompt-templates.show', selectedTemplate.id)" class="btn-primary">Open editor</Link>
                                                <Link :href="selectedTemplateRunHref" class="btn-secondary">Run template</Link>
                                            </div>
                                        </div>

                                        <div class="console-detail-section">
                                            <div class="key-value-grid">
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Task</div>
                                                    <div class="key-value-value">{{ selectedTemplate.use_case?.name || 'No task assigned' }}</div>
                                                </div>
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Task type</div>
                                                    <div class="key-value-value capitalize">{{ selectedTemplate.task_type }}</div>
                                                </div>
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Preferred model</div>
                                                    <div class="key-value-value mono text-sm">{{ selectedTemplate.preferred_model || 'No preferred model' }}</div>
                                                </div>
                                                <div class="key-value-item">
                                                    <div class="key-value-label">Average score</div>
                                                    <div class="key-value-value">{{ formatScore(selectedTemplate.average_score) }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="console-detail-section">
                                            <div class="console-field-label">Approval</div>
                                            <div class="surface-muted mt-3">
                                                <div class="font-semibold text-[var(--ink)]">
                                                    {{ selectedTemplate.approval_state === 'approved' ? 'Approved' : 'Pending approval' }}
                                                </div>
                                                <div v-if="selectedTemplate.approved_version_label" class="mt-1 text-sm text-[var(--muted)]">
                                                    {{ selectedTemplate.approved_version_label }}
                                                    <span v-if="selectedTemplate.approved_by"> by {{ selectedTemplate.approved_by }}</span>
                                                </div>
                                                <div v-if="selectedTemplate.approved_at" class="mt-1 text-sm text-[var(--muted)]">
                                                    {{ formatDateTime(selectedTemplate.approved_at) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="console-detail-section">
                                            <div class="console-field-label">Registry summary</div>
                                            <div class="surface-muted mt-3">
                                                <div class="inline-meta">
                                                    <span class="inline-meta-item">
                                                        <ClipboardList />
                                                        {{ selectedTemplate.versions_count }} versions
                                                    </span>
                                                    <span v-if="selectedTemplate.created_by" class="inline-meta-item">
                                                        {{ selectedTemplate.created_by }}
                                                    </span>
                                                    <span v-if="selectedTemplate.latest_version_label" class="inline-meta-item">
                                                        {{ selectedTemplate.latest_version_label }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <div v-else class="console-empty-pane">
                                        No prompt templates match the current filters.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
