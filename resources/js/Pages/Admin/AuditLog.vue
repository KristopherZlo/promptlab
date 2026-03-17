<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterDropdown from '@/Components/FilterDropdown.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import { History } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/formatters';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    entries: {
        type: Array,
        required: true,
    },
});

const search = ref('');
const action = ref('');

const actions = computed(() =>
    [...new Set(props.entries.map((entry) => entry.action).filter(Boolean))].sort((left, right) => left.localeCompare(right)),
);

const filteredEntries = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.entries.filter((entry) => {
        const matchesAction = !action.value || entry.action === action.value;
        const haystack = [
            entry.action,
            entry.actor,
            entry.subject_label,
            JSON.stringify(entry.details_json ?? {}),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        const matchesSearch = !query || haystack.includes(query);

        return matchesAction && matchesSearch;
    });
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
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Workspace</div>
                            <div class="summary-item-value">{{ team.name }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Visible entries</div>
                            <div class="summary-item-value">{{ filteredEntries.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Loaded entries</div>
                            <div class="summary-item-value">{{ entries.length }}</div>
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
                        :model-value="search"
                        placeholder="Search actor, action, or subject..."
                        @update:model-value="search = $event"
                    >
                        <FilterDropdown
                            label="Action"
                            :icon="History"
                            :options="actions.map((item) => ({ label: item, value: item }))"
                            :selected="action"
                            :selected-label="action"
                            width="240px"
                            @select="action = $event"
                            @clear="action = ''"
                        />
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
                        <tr v-for="entry in filteredEntries" :key="entry.id">
                            <td class="font-semibold">{{ entry.action }}</td>
                            <td>{{ entry.actor || 'System' }}</td>
                            <td>{{ entry.subject_label || 'Workspace event' }}</td>
                            <td class="text-sm text-[var(--muted)]">{{ formatDateTime(entry.created_at) }}</td>
                            <td>
                                <pre v-if="Object.keys(entry.details_json ?? {}).length" class="code-block text-xs">{{ JSON.stringify(entry.details_json, null, 2) }}</pre>
                                <span v-else class="text-sm text-[var(--muted)]">No details</span>
                            </td>
                        </tr>
                        <tr v-if="filteredEntries.length === 0">
                            <td colspan="5" class="text-[var(--muted)]">No audit entries match the current filter.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
