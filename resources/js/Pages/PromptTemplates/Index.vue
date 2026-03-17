<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { ClipboardList, ListFilter } from 'lucide-vue-next';
import { formatDateTime, formatScore } from '@/lib/formatters';

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

const taskTypes = ['summarization', 'classification', 'rewrite', 'extraction', 'generation'];
const statuses = ['active', 'draft', 'archived'];

const filterForm = reactive({
    use_case_id: props.filters.use_case_id ?? '',
    task_type: props.filters.task_type ?? '',
    status: props.filters.status ?? '',
    author: props.filters.author ?? '',
    preferred_model: props.filters.preferred_model ?? '',
});

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

const cleanedFilters = () =>
    Object.fromEntries(
        Object.entries(filterForm)
            .map(([key, value]) => [key, `${value ?? ''}`.trim()])
            .filter(([, value]) => value !== ''),
    );

const submitFilters = () => {
    router.get(route('prompt-templates.index'), cleanedFilters(), {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    Object.assign(filterForm, {
        use_case_id: '',
        task_type: '',
        status: '',
        author: '',
        preferred_model: '',
    });

    router.get(route('prompt-templates.index'), {}, {
        preserveState: true,
        replace: true,
    });
};

const statusBreakdown = computed(() =>
    statuses.map((status) => ({
        status,
        count: props.templates.filter((template) => template.status === status).length,
    })),
);
</script>

<template>
    <Head title="Prompt Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Prompt Templates</h1>
                    <p class="mt-1 text-sm text-[var(--muted)]">Prompt families, version history, and approval state.</p>
                </div>
                <Link :href="route('prompt-templates.create')" class="btn-primary">New template</Link>
            </div>
        </template>

        <div class="space-y-6">
            <form class="panel p-4" @submit.prevent="submitFilters">
                <PanelHeader
                    title="Filter prompt templates"
                    description="Use filters to find the right prompt family quickly."
                    :icon="ListFilter"
                />
                <div class="summary-strip mt-4">
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
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="field-label">Task</label>
                        <select v-model="filterForm.use_case_id" class="field-select">
                            <option value="">All tasks</option>
                            <option v-for="useCase in useCases" :key="useCase.id" :value="useCase.id">
                                {{ useCase.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Task type</label>
                        <select v-model="filterForm.task_type" class="field-select">
                            <option value="">All task types</option>
                            <option v-for="taskType in taskTypes" :key="taskType" :value="taskType">
                                {{ taskType }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Status</label>
                        <select v-model="filterForm.status" class="field-select">
                            <option value="">All statuses</option>
                            <option v-for="status in statuses" :key="status" :value="status">
                                {{ status }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Author</label>
                        <input v-model="filterForm.author" type="text" class="field-input" placeholder="Filter by author name">
                    </div>
                    <div>
                        <label class="field-label">Preferred model</label>
                        <select v-model="filterForm.preferred_model" class="field-select">
                            <option value="">All models</option>
                            <option v-for="model in preferredModels" :key="model" :value="model">
                                {{ model }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="btn-secondary">Apply filters</button>
                    <button v-if="hasFilters" type="button" class="btn-ghost" @click="resetFilters">Reset</button>
                </div>
            </form>

            <section v-if="templates.length" class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Prompt template list"
                        description="Open a row to manage version history and library approval."
                        :icon="ClipboardList"
                    />
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Task</th>
                            <th>Type</th>
                            <th>Approval</th>
                            <th>Versions</th>
                            <th>Average score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="template in templates" :key="template.id">
                            <td>
                                <Link :href="route('prompt-templates.show', template.id)" class="font-bold hover:underline">
                                    {{ template.name }}
                                </Link>
                                <div class="mt-1 text-sm text-[var(--muted)]">{{ template.description || 'No description yet.' }}</div>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-[var(--muted)]">
                                    <span class="status-chip">{{ template.status }}</span>
                                    <span v-if="template.latest_version_label" class="status-chip">{{ template.latest_version_label }}</span>
                                    <span v-if="template.created_by" class="status-chip">{{ template.created_by }}</span>
                                </div>
                            </td>
                            <td>{{ template.use_case?.name || 'N/A' }}</td>
                            <td class="capitalize">{{ template.task_type }}</td>
                            <td>
                                <div class="text-sm">
                                    <div class="font-bold">
                                        {{ template.approval_state === 'approved' ? 'Approved' : 'Pending approval' }}
                                    </div>
                                    <div v-if="template.approved_version_label" class="mt-1 text-xs text-[var(--muted)]">
                                        {{ template.approved_version_label }}
                                        <span v-if="template.approved_by"> by {{ template.approved_by }}</span>
                                    </div>
                                    <div v-if="template.approved_at" class="mt-1 text-xs text-[var(--muted)]">
                                        {{ formatDateTime(template.approved_at) }}
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>{{ template.versions_count }}</div>
                                <div class="mt-1 text-xs text-[var(--muted)]">
                                    {{ template.preferred_model || 'No preferred model' }}
                                </div>
                            </td>
                            <td>{{ formatScore(template.average_score) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div v-else class="empty-state">
                No prompt templates match the current filters.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
