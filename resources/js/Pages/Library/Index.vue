<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import { BookCopy } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/formatters';
import { routeWithQuery } from '@/lib/urlState';

const props = defineProps({
    entries: {
        type: Array,
        required: true,
    },
    canManage: {
        type: Boolean,
        required: true,
    },
});

const search = ref('');

const filteredEntries = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.entries;
    }

    return props.entries.filter((entry) => {
        const haystack = [
            entry.prompt_version?.name,
            entry.prompt_version?.version_label,
            entry.prompt_version?.use_case,
            entry.recommended_model,
            entry.best_for,
            entry.usage_notes,
            entry.approved_by,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const groupedCounts = computed(() => {
    const counts = {};

    for (const entry of props.entries) {
        const key = entry.prompt_version?.use_case || 'Unassigned';
        counts[key] = (counts[key] ?? 0) + 1;
    }

    return Object.keys(counts).length;
});

const entryApprovalHref = (entry) =>
    entry.prompt_version?.prompt_template_id
        ? routeWithQuery('prompt-templates.show', entry.prompt_version.prompt_template_id, {
            tab: 'library',
            prompt_version_id: entry.prompt_version?.id,
        })
        : route('prompt-templates.index');
const entrySourceHref = (entry) =>
    entry.prompt_version?.prompt_template_id
        ? routeWithQuery('prompt-templates.show', entry.prompt_version.prompt_template_id, {
            tab: 'versions',
            prompt_version_id: entry.prompt_version?.id,
        })
        : route('prompt-templates.index');

const entryRunHref = (entry) =>
    routeWithQuery('playground', {}, {
        mode: 'single',
        use_case_id: entry.prompt_version?.use_case_id,
        prompt_template_id: entry.prompt_version?.prompt_template_id,
        prompt_version_id: entry.prompt_version?.id,
        model_name: entry.recommended_model || entry.prompt_version?.preferred_model || '',
    });

const entryDetailHref = (entry) => route('library.show', entry.id);
</script>

<template>
    <Head title="Approved Library" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-lead">
                <h1 class="text-2xl font-semibold tracking-tight">Library</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Reusable prompt versions that the team has already approved for wider use.
                </p>
            </div>
        </template>

        <div class="page-frame-content">
            <section class="panel p-5">
                <PanelHeader
                    title="Library snapshot"
                    description="Coverage, visibility, and quick actions for the shared prompt library."
                    help="Shows how many shared prompt entries exist, how broadly they cover tasks, and what portion is currently visible."
                />

                <div class="toolbar mt-4">
                    <div class="summary-strip library-snapshot-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Shared entries</div>
                            <div class="summary-item-value">{{ entries.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Tasks covered</div>
                            <div class="summary-item-value">{{ groupedCounts }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Visible now</div>
                            <div class="summary-item-value">{{ filteredEntries.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Access</div>
                            <div class="summary-item-value">{{ canManage ? 'Manage' : 'Read only' }}</div>
                        </div>
                    </div>

                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Shared prompt catalog"
                        description="A dense, searchable list of prompt versions ready for reuse."
                        :icon="BookCopy"
                        help="Provides the shared catalog of prompt versions that teams can reuse with less review overhead."
                    />
                </div>

                <div class="px-5 py-4">
                    <SearchFilterBar
                        :model-value="search"
                        placeholder="Search by task, prompt, model, or note..."
                        @update:model-value="search = $event"
                    />
                </div>

                <table v-if="filteredEntries.length" class="data-table">
                    <thead>
                        <tr>
                            <th>Prompt</th>
                            <th>Task</th>
                            <th>Recommended model</th>
                            <th>Best for</th>
                            <th>Approved by</th>
                            <th>Approved at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in filteredEntries" :key="entry.id">
                            <td>
                                <Link :href="entryDetailHref(entry)" class="font-semibold hover:underline">
                                    {{ entry.prompt_version?.name }} {{ entry.prompt_version?.version_label }}
                                </Link>
                                <div class="mt-1 text-sm text-[var(--muted)]">
                                    {{ entry.usage_notes || 'No additional usage notes.' }}
                                </div>
                                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                                    <Link :href="entrySourceHref(entry)" class="app-inline-link">View prompt version</Link>
                                    <Link :href="entryApprovalHref(entry)" class="app-inline-link">Open library handoff</Link>
                                    <Link :href="entryRunHref(entry)" class="app-inline-link">Run experiments</Link>
                                </div>
                            </td>
                            <td>{{ entry.prompt_version?.use_case || 'No task' }}</td>
                            <td class="mono text-xs">{{ entry.recommended_model || 'No override' }}</td>
                            <td class="text-sm text-[var(--muted)]">{{ entry.best_for || 'General internal use' }}</td>
                            <td>{{ entry.approved_by || 'Unknown reviewer' }}</td>
                            <td class="text-sm text-[var(--muted)]">{{ formatDateTime(entry.approved_at) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="empty-state mx-5 mb-5">
                    No shared prompts match the current search.
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
