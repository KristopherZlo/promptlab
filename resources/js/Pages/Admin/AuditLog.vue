<script setup>
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterDropdown from '@/Components/FilterDropdown.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import { ArrowDownWideNarrow, History } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/formatters';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    entries: {
        type: Object,
        required: true,
    },
    actions: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const filterForm = reactive({
    search: props.filters.search ?? '',
    action: props.filters.action ?? '',
    sort: props.filters.sort ?? 'newest',
});
let searchTimer = null;

const actionOptions = computed(() =>
    props.actions.map((item) => ({
        label: item,
        value: item,
    })),
);

const sortOptions = [
    { label: 'Newest first', value: 'newest' },
    { label: 'Oldest first', value: 'oldest' },
];

const sortLabel = computed(() =>
    sortOptions.find((option) => option.value === filterForm.sort)?.label ?? 'Newest first',
);

const entriesMeta = computed(() => props.entries.meta ?? {});
const rows = computed(() => props.entries.data ?? []);
const visibleCount = computed(() => entriesMeta.value.total ?? rows.value.length);
const loadedCount = computed(() => rows.value.length);
const currentPage = computed(() => entriesMeta.value.current_page ?? 1);
const lastPage = computed(() => entriesMeta.value.last_page ?? 1);
const hasFilters = computed(() =>
    filterForm.search.trim() !== '' || filterForm.action !== '' || filterForm.sort !== 'newest',
);

const pageItems = computed(() => {
    const current = currentPage.value;
    const last = lastPage.value;

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => ({
            key: `page-${index + 1}`,
            type: 'page',
            value: index + 1,
        }));
    }

    const pages = new Set([1, last, current - 1, current, current + 1]);

    if (current <= 3) {
        pages.add(2);
        pages.add(3);
        pages.add(4);
    }

    if (current >= last - 2) {
        pages.add(last - 1);
        pages.add(last - 2);
        pages.add(last - 3);
    }

    const sortedPages = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((left, right) => left - right);

    const items = [];
    let previousPage = null;

    sortedPages.forEach((page) => {
        if (previousPage !== null && page - previousPage > 1) {
            items.push({
                key: `ellipsis-${previousPage}-${page}`,
                type: 'ellipsis',
            });
        }

        items.push({
            key: `page-${page}`,
            type: 'page',
            value: page,
        });

        previousPage = page;
    });

    return items;
});

const cleanedFilters = (overrides = {}) =>
    Object.fromEntries(
        Object.entries({
            search: filterForm.search,
            action: filterForm.action,
            sort: filterForm.sort,
            ...overrides,
        })
            .map(([key, value]) => [key, `${value ?? ''}`.trim()])
            .filter(([, value]) => value !== '' && !(key === 'sort' && value === 'newest')),
    );

const visit = (overrides = {}, options = {}) => {
    router.get(route('admin.audit-log'), cleanedFilters(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: options.replace ?? true,
        only: ['entries', 'filters', 'actions'],
    });
};

const updateAction = (value) => {
    filterForm.action = value;
    visit({ action: value, page: 1 });
};

const updateSort = (value) => {
    filterForm.sort = value;
    visit({ sort: value, page: 1 });
};

const resetFilters = () => {
    window.clearTimeout(searchTimer);

    filterForm.search = '';
    filterForm.action = '';
    filterForm.sort = 'newest';

    visit({ search: '', action: '', sort: 'newest', page: 1 });
};

const goToPage = (page) => {
    if (page < 1 || page > lastPage.value || page === currentPage.value) {
        return;
    }

    visit({ page }, { replace: false });
};

watch(
    () => props.filters,
    (nextFilters) => {
        filterForm.search = nextFilters.search ?? '';
        filterForm.action = nextFilters.action ?? '';
        filterForm.sort = nextFilters.sort ?? 'newest';
    },
    { deep: true },
);

watch(
    () => filterForm.search,
    () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            visit({ page: 1 });
        }, 240);
    },
);

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Audit Log" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-lead">
                <h1 class="text-2xl font-semibold tracking-tight">Audit Log</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Trace administrative changes inside {{ team.name }}.
                </p>
            </div>
        </template>

        <div class="page-frame-content">
            <section class="panel p-5">
                <PanelHeader
                    title="Audit snapshot"
                    description="Visible event totals for the current workspace and the active filter result."
                    help="Summarizes audit volume so administrators can quickly see how much activity is loaded and how much remains after filtering."
                />

                <div class="toolbar mt-4">
                    <div class="summary-strip library-snapshot-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Workspace</div>
                            <div class="summary-item-value">{{ team.name }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Visible entries</div>
                            <div class="summary-item-value">{{ visibleCount }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Loaded entries</div>
                            <div class="summary-item-value">{{ loadedCount }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Action types</div>
                            <div class="summary-item-value">{{ actions.length }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Administrative events"
                        description="A compact record of who changed what and when."
                        :icon="History"
                        help="Main audit table for tracing administrative actions, who performed them, and when they happened."
                    />
                </div>

                <div class="px-5 py-4">
                    <SearchFilterBar
                        :model-value="filterForm.search"
                        placeholder="Search actor, action, or subject..."
                        @update:model-value="filterForm.search = $event"
                    >
                        <FilterDropdown
                            label="Action"
                            :icon="History"
                            :options="actionOptions"
                            :selected="filterForm.action"
                            :selected-label="filterForm.action"
                            width="240px"
                            @select="updateAction($event)"
                            @clear="updateAction('')"
                        />
                        <FilterDropdown
                            label="Date"
                            :icon="ArrowDownWideNarrow"
                            :options="sortOptions"
                            :selected="filterForm.sort"
                            :selected-label="sortLabel"
                            width="220px"
                            :clearable="false"
                            @select="updateSort($event)"
                        />
                        <button v-if="hasFilters" type="button" class="filter-toolbar-reset" @click="resetFilters">
                            Reset
                        </button>
                    </SearchFilterBar>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Actor</th>
                            <th>Subject</th>
                            <th>When</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in rows" :key="entry.id">
                            <td class="font-semibold">{{ entry.action }}</td>
                            <td>{{ entry.actor || 'System' }}</td>
                            <td>{{ entry.subject_label || 'Workspace event' }}</td>
                            <td class="text-sm text-[var(--muted)]">{{ formatDateTime(entry.created_at) }}</td>
                            <td>
                                <pre v-if="Object.keys(entry.details_json ?? {}).length" class="code-block text-xs">{{ JSON.stringify(entry.details_json, null, 2) }}</pre>
                                <span v-else class="text-sm text-[var(--muted)]">No details</span>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="5" class="text-[var(--muted)]">No audit entries match the current filter.</td>
                        </tr>
                    </tbody>
                </table>

                <div
                    v-if="lastPage > 1"
                    class="flex flex-col gap-3 border-t border-[var(--line)] px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="text-sm text-[var(--muted)]">
                        Showing {{ entriesMeta.from ?? 0 }}-{{ entriesMeta.to ?? 0 }} of {{ entriesMeta.total ?? 0 }} entries
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="btn-secondary"
                            :disabled="currentPage <= 1"
                            @click="goToPage(currentPage - 1)"
                        >
                            Previous
                        </button>

                        <template v-for="item in pageItems" :key="item.key">
                            <span
                                v-if="item.type === 'ellipsis'"
                                class="px-2 text-sm text-[var(--muted)]"
                            >
                                ...
                            </span>
                            <button
                                v-else
                                type="button"
                                :class="item.value === currentPage ? 'btn-primary' : 'btn-secondary'"
                                @click="goToPage(item.value)"
                            >
                                {{ item.value }}
                            </button>
                        </template>

                        <button
                            type="button"
                            class="btn-secondary"
                            :disabled="currentPage >= lastPage"
                            @click="goToPage(currentPage + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
