<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterDropdown from '@/Components/FilterDropdown.vue';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import {
    BadgeCheck,
    Bot,
    ClipboardList,
    Filter,
    FolderKanban,
    Gauge,
    Layers3,
    RotateCcw,
    UserRound,
    X,
} from 'lucide-vue-next';
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
    collections: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const taskTypes = ['summarization', 'classification', 'rewrite', 'extraction', 'generation'];
const statuses = ['active', 'draft', 'archived'];
const sortOptions = [
    { label: 'Recently updated', value: 'recent' },
    { label: 'Highest score', value: 'score' },
    { label: 'Most reviewed', value: 'reviewed' },
    { label: 'Most versions', value: 'versions' },
    { label: 'Name', value: 'name' },
];

const selectedTemplateId = ref(null);
const sortBy = ref('recent');

const filterForm = reactive({
    search: props.filters.search ?? '',
    use_case_id: props.filters.use_case_id ?? '',
    task_type: props.filters.task_type ?? '',
    status: props.filters.status ?? '',
    author: props.filters.author ?? '',
    preferred_model: props.filters.preferred_model ?? '',
});

const canManageTemplates = computed(() => (page.props.auth?.abilities ?? []).includes('manage_prompts'));
const taskTypeOptions = taskTypes.map((taskType) => ({ label: taskType, value: taskType }));
const statusOptions = statuses.map((status) => ({ label: status, value: status }));
const formatUseCaseName = (name) => {
    if (!name) {
        return '';
    }

    const agencyAgentsPrefix = 'Agency Agents / ';

    if (!name.startsWith(agencyAgentsPrefix)) {
        return name;
    }

    return `${name.slice(agencyAgentsPrefix.length)} / Agency Agents`;
};

const useCaseLabel = computed(() =>
    formatUseCaseName(props.useCases.find((useCase) => `${useCase.id}` === `${filterForm.use_case_id}`)?.name ?? ''),
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

const libraryReadyCount = computed(() =>
    props.templates.filter((template) => template.approval_state === 'approved').length,
);
const pendingApprovalCount = computed(() => props.templates.length - libraryReadyCount.value);
const collectionOptions = computed(() => props.collections);
const collectionTotal = computed(() =>
    collectionOptions.value.reduce((total, collection) => total + (collection.count ?? 0), 0),
);

const activeFilterSummary = computed(() =>
    [
        filterForm.search ? `Search: ${filterForm.search}` : null,
        useCaseLabel.value ? `Task: ${useCaseLabel.value}` : null,
        taskTypeLabel.value ? `Type: ${taskTypeLabel.value}` : null,
        statusLabel.value ? `Status: ${statusLabel.value}` : null,
        filterForm.preferred_model ? `Model: ${filterForm.preferred_model}` : null,
        filterForm.author ? `Author: ${filterForm.author}` : null,
    ].filter(Boolean),
);

const sortedTemplates = computed(() => {
    const items = [...props.templates];

    switch (sortBy.value) {
        case 'score':
            return items.sort((left, right) =>
                (right.average_score ?? -1) - (left.average_score ?? -1)
                || left.name.localeCompare(right.name),
            );
        case 'reviewed':
            return items.sort((left, right) =>
                (right.reviewed_runs ?? 0) - (left.reviewed_runs ?? 0)
                || left.name.localeCompare(right.name),
            );
        case 'versions':
            return items.sort((left, right) =>
                (right.versions_count ?? 0) - (left.versions_count ?? 0)
                || left.name.localeCompare(right.name),
            );
        case 'name':
            return items.sort((left, right) => left.name.localeCompare(right.name));
        default:
            return items.sort((left, right) =>
                Date.parse(right.updated_at ?? right.created_at ?? '') - Date.parse(left.updated_at ?? left.created_at ?? '')
                || left.name.localeCompare(right.name),
            );
    }
});

const selectedTemplate = computed(() =>
    sortedTemplates.value.find((template) => template.id === selectedTemplateId.value) ?? sortedTemplates.value[0] ?? null,
);
const selectedTemplateLatestVersion = computed(() => selectedTemplate.value?.versions?.at(-1) ?? null);
const selectedTemplateApprovedVersion = computed(() =>
    [...(selectedTemplate.value?.versions ?? [])].reverse().find((version) => version.is_library_approved) ?? null,
);
const selectedTemplateVersions = computed(() =>
    [...(selectedTemplate.value?.versions ?? [])].reverse().slice(0, 5),
);

const templatePrimaryModel = (template) =>
    template.preferred_model
    || template.versions?.at(-1)?.preferred_model
    || template.versions?.find((version) => version.is_library_approved)?.library_entry?.recommended_model
    || '';

const templateApprovalLabel = (template) =>
    template.approval_state === 'approved' ? 'Library ready' : template.status;

const templateRunHref = (template) => {
    const latestVersionId = template.versions?.at(-1)?.id ?? '';

    return routeWithQuery('playground', {}, {
        mode: 'single',
        use_case_id: template.use_case_id,
        prompt_template_id: template.id,
        prompt_version_id: latestVersionId,
    });
};

const selectedTemplateRunHref = computed(() =>
    selectedTemplate.value ? templateRunHref(selectedTemplate.value) : route('playground'),
);

watch(
    () => sortedTemplates.value.map((item) => item.id),
    (ids) => {
        if (!ids.length) {
            selectedTemplateId.value = null;
            return;
        }

        if (!ids.includes(selectedTemplateId.value)) {
            selectedTemplateId.value = ids[0];
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
        only: ['templates', 'filters', 'useCases', 'collections'],
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
        only: ['templates', 'filters', 'useCases', 'collections'],
    });
};

watch(() => props.filters.search, (value) => {
    const nextValue = value ?? '';

    if (filterForm.search !== nextValue) {
        suspendTextRequests = true;
        filterForm.search = nextValue;
        suspendTextRequests = false;
    }
});

watch(() => props.filters.author, (value) => {
    const nextValue = value ?? '';

    if (filterForm.author !== nextValue) {
        suspendTextRequests = true;
        filterForm.author = nextValue;
        suspendTextRequests = false;
    }
});

watch(() => filterForm.search, () => {
    if (suspendTextRequests) {
        return;
    }

    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        pushFilters();
    }, 240);
});

watch(() => filterForm.author, () => {
    if (suspendTextRequests) {
        return;
    }

    window.clearTimeout(authorTimer);
    authorTimer = window.setTimeout(() => {
        pushFilters();
    }, 240);
});

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer);
    window.clearTimeout(authorTimer);
});
</script>

<template>
    <Head title="Prompts" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Prompts</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Browse the prompt catalog as a working library, not a flat list.
                </p>
            </div>
        </template>

        <div class="page-frame-content">
            <section class="surface-block">
                <div class="surface-block-header">
                    <div>
                        <h2 class="section-title">Prompt catalog</h2>
                        <p class="text-sm text-[var(--muted)]">
                            Search, narrow, sort, and open reusable prompts from a single library view.
                        </p>
                    </div>
                    <div class="console-page-actions">
                        <Link v-if="canManageTemplates" :href="route('prompt-templates.create')" class="btn-primary">Add prompt</Link>
                    </div>
                </div>

                <div class="surface-block-body space-y-5">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Visible prompts</div>
                            <div class="summary-item-value">{{ sortedTemplates.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Library ready</div>
                            <div class="summary-item-value">{{ libraryReadyCount }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Pending approval</div>
                            <div class="summary-item-value">{{ pendingApprovalCount }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Collections</div>
                            <div class="summary-item-value">{{ collectionOptions.length }}</div>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px]">
                        <SearchFilterBar
                            :model-value="filterForm.search"
                            placeholder="Search prompts by name, description, or notes..."
                            @update:model-value="filterForm.search = $event"
                        >
                            <FilterDropdown
                                label="Task"
                                :icon="FolderKanban"
                                :options="useCases.map((useCase) => ({ label: formatUseCaseName(useCase.name), value: useCase.id }))"
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
                                        <X class="h-4 w-4" />
                                        <span>Clear</span>
                                    </button>
                                </template>
                            </FilterDropdown>
                            <button
                                v-if="hasFilters"
                                type="button"
                                class="filter-toolbar-reset filter-toolbar-reset-icon"
                                title="Reset filters"
                                aria-label="Reset filters"
                                @click="resetFilters"
                            >
                                <RotateCcw class="h-4 w-4" />
                            </button>
                        </SearchFilterBar>

                        <div>
                            <label class="field-label">Sort</label>
                            <select v-model="sortBy" class="field-select">
                                <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="activeFilterSummary.length" class="flex flex-wrap gap-2 text-sm text-[var(--muted)]">
                        <span v-for="item in activeFilterSummary" :key="item" class="role-badge">
                            {{ item }}
                        </span>
                    </div>
                </div>
            </section>

            <div class="prompt-library-shell">
                <aside class="panel p-4 xl:sticky xl:top-4 xl:self-start">
                    <div class="prompt-library-rail-title">Collections</div>
                    <div class="mt-3 space-y-2">
                        <button
                            type="button"
                            class="prompt-library-collection"
                            :class="{ 'prompt-library-collection-active': !filterForm.use_case_id }"
                            @click="updateFilter('use_case_id', '')"
                        >
                            <span>All prompts</span>
                            <span class="text-sm text-[var(--muted)]">{{ collectionTotal }}</span>
                        </button>

                        <button
                            v-for="collection in collectionOptions"
                            :key="collection.id"
                            type="button"
                            class="prompt-library-collection"
                            :class="{ 'prompt-library-collection-active': `${collection.id}` === `${filterForm.use_case_id}` }"
                            @click="updateFilter('use_case_id', collection.id)"
                        >
                            <span class="truncate text-[13px] leading-5">{{ formatUseCaseName(collection.name) }}</span>
                            <span class="text-sm text-[var(--muted)]">{{ collection.count }}</span>
                        </button>
                    </div>

                    <div class="mt-5 border-t border-[var(--line)] pt-4">
                        <div class="prompt-library-rail-title">Status</div>
                        <div class="mt-3 space-y-2 text-sm text-[var(--muted)]">
                            <div class="flex items-center justify-between gap-3">
                                <span>Library ready</span>
                                <span>{{ libraryReadyCount }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Pending approval</span>
                                <span>{{ pendingApprovalCount }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Visible now</span>
                                <span>{{ sortedTemplates.length }}</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="space-y-4">
                    <div v-if="sortedTemplates.length" class="prompt-library-card-grid">
                        <article
                            v-for="template in sortedTemplates"
                            :key="template.id"
                            class="prompt-library-card"
                            :class="{ 'prompt-library-card-active': template.id === selectedTemplate?.id }"
                        >
                            <button type="button" class="w-full text-left" @click="selectedTemplateId = template.id">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-[var(--ink)]">{{ template.name }}</div>
                                        <div class="mt-1 text-[13px] leading-5 text-[var(--muted)]">
                                            {{ formatUseCaseName(template.use_case?.name) || 'No task assigned' }}
                                        </div>
                                    </div>
                                    <span class="status-chip">{{ templateApprovalLabel(template) }}</span>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-[var(--muted)]">
                                    {{ template.description || 'No prompt description has been added yet.' }}
                                </p>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="guide-card">
                                        <div class="inline-meta-item text-xs text-[var(--muted)]">
                                            <Gauge />
                                            <span>Score</span>
                                        </div>
                                        <div class="mt-1 font-semibold">{{ formatScore(template.average_score) }}</div>
                                    </div>
                                    <div class="guide-card">
                                        <div class="inline-meta-item text-xs text-[var(--muted)]">
                                            <Layers3 />
                                            <span>Versions</span>
                                        </div>
                                        <div class="mt-1 font-semibold">{{ template.versions_count }}</div>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span
                                        v-for="tag in (template.tags_json ?? []).slice(0, 4)"
                                        :key="`${template.id}-${tag}`"
                                        class="role-badge"
                                    >
                                        {{ tag }}
                                    </span>
                                    <span v-if="(template.tags_json ?? []).length > 4" class="role-badge">
                                        +{{ template.tags_json.length - 4 }}
                                    </span>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-3 text-xs text-[var(--muted)]">
                                    <span class="inline-meta-item">
                                        <Bot />
                                        {{ templatePrimaryModel(template) || 'No preferred model' }}
                                    </span>
                                    <span class="inline-meta-item">
                                        <BadgeCheck />
                                        {{ template.reviewer_count || 0 }} reviewers
                                    </span>
                                </div>
                            </button>

                            <div class="prompt-library-card-actions">
                                <Link :href="route('prompt-templates.show', template.id)" class="app-inline-link">
                                    Open prompt
                                </Link>
                                <Link :href="templateRunHref(template)" class="app-inline-link">
                                    Test prompt
                                </Link>
                            </div>
                        </article>
                    </div>

                    <div v-else class="panel p-5 text-sm text-[var(--muted)]">
                        No prompt templates match the current filters.
                    </div>
                </section>

                <aside v-if="selectedTemplate" class="panel p-5 xl:sticky xl:top-4 xl:self-start">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[13px] leading-5 text-[var(--muted)]">
                                {{ formatUseCaseName(selectedTemplate.use_case?.name) || 'No task assigned' }}
                            </div>
                            <h2 class="mt-2 text-xl font-semibold tracking-tight text-[var(--ink)]">
                                {{ selectedTemplate.name }}
                            </h2>
                        </div>
                        <span class="status-chip">{{ templateApprovalLabel(selectedTemplate) }}</span>
                    </div>

                    <p class="mt-3 text-sm leading-6 text-[var(--muted)]">
                        {{ selectedTemplate.description || 'No prompt description has been added yet.' }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <Link :href="route('prompt-templates.show', selectedTemplate.id)" class="btn-primary">Open prompt</Link>
                        <Link :href="selectedTemplateRunHref" class="btn-secondary">Test prompt</Link>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div class="key-value-item">
                            <div class="key-value-label">Task type</div>
                            <div class="key-value-value capitalize">{{ selectedTemplate.task_type }}</div>
                        </div>
                        <div class="key-value-item">
                            <div class="key-value-label">Preferred model</div>
                            <div class="key-value-value mono text-sm">{{ templatePrimaryModel(selectedTemplate) || 'No preferred model' }}</div>
                        </div>
                        <div class="key-value-item">
                            <div class="key-value-label">Average score</div>
                            <div class="key-value-value">{{ formatScore(selectedTemplate.average_score) }}</div>
                        </div>
                        <div class="key-value-item">
                            <div class="key-value-label">Reviewed runs</div>
                            <div class="key-value-value">{{ selectedTemplate.reviewed_runs || 0 }}</div>
                        </div>
                    </div>

                    <div class="console-detail-section">
                        <div class="console-field-label">Approval</div>
                        <div class="surface-muted mt-3">
                            <div class="font-semibold text-[var(--ink)]">
                                {{ selectedTemplate.approval_state === 'approved' ? 'Approved for library reuse' : 'Still pending approval' }}
                            </div>
                            <div v-if="selectedTemplateApprovedVersion" class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                {{ selectedTemplateApprovedVersion.version_label }}
                                <span v-if="selectedTemplate.approved_by"> by {{ selectedTemplate.approved_by }}</span>
                            </div>
                            <div v-if="selectedTemplate.approved_at" class="mt-1 text-sm text-[var(--muted)]">
                                {{ formatDateTime(selectedTemplate.approved_at) }}
                            </div>
                        </div>
                    </div>

                    <div class="console-detail-section">
                        <div class="console-field-label">Tags</div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="tag in selectedTemplate.tags_json ?? []"
                                :key="`detail-${selectedTemplate.id}-${tag}`"
                                class="role-badge"
                            >
                                {{ tag }}
                            </span>
                            <span v-if="!(selectedTemplate.tags_json ?? []).length" class="text-sm text-[var(--muted)]">
                                No tags
                            </span>
                        </div>
                    </div>

                    <div class="console-detail-section">
                        <div class="console-field-label">Version shelf</div>
                        <div class="prompt-library-version-list mt-3">
                            <div
                                v-for="version in selectedTemplateVersions"
                                :key="version.id"
                                class="guide-card p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="font-semibold text-[var(--ink)]">{{ version.version_label }}</div>
                                    <div class="text-xs text-[var(--muted)]">{{ formatDateTime(version.created_at) }}</div>
                                </div>
                                <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                    {{ version.change_summary || version.notes || 'No version summary yet.' }}
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs text-[var(--muted)]">
                                    <span class="inline-meta-item">
                                        <Bot />
                                        {{ version.preferred_model || selectedTemplate.preferred_model || 'No model' }}
                                    </span>
                                    <span class="inline-meta-item">
                                        <Gauge />
                                        {{ formatScore(version.average_score) }}
                                    </span>
                                    <span class="inline-meta-item">
                                        <BadgeCheck />
                                        {{ version.is_library_approved ? 'Library approved' : 'Not approved' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedTemplateLatestVersion" class="mt-4 text-sm text-[var(--muted)]">
                            Latest version: {{ selectedTemplateLatestVersion.version_label }}
                            <span v-if="selectedTemplateLatestVersion.created_by"> by {{ selectedTemplateLatestVersion.created_by }}</span>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
