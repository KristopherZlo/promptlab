<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { BookCopy, Search } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/formatters';

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
</script>

<template>
    <Head title="Approved Library" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-lead">
                <h1 class="text-2xl font-semibold tracking-tight">Approved Library</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Approved prompt versions ready for controlled reuse.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="toolbar">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Approved entries</div>
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

                    <div class="toolbar-actions">
                        <Link :href="route('prompt-templates.index')" class="btn-secondary">Review templates</Link>
                        <Link :href="route('playground')" class="btn-primary">Open experiments</Link>
                    </div>
                </div>
            </section>

            <section class="panel p-4">
                <PanelHeader
                    title="Search approved entries"
                    description="Search by task, prompt name, model, or approval note."
                    :icon="Search"
                />
                <div class="mt-4 table-toolbar">
                    <input v-model="search" type="text" class="field-input md:max-w-sm" placeholder="Task, prompt, model, or note">
                </div>
            </section>

            <section v-if="filteredEntries.length" class="panel overflow-hidden">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <PanelHeader
                        title="Approved prompt catalog"
                        description="Dense operational catalog instead of card-based browsing."
                        :icon="BookCopy"
                    />
                </div>

                <table class="data-table">
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
                                <div class="font-semibold">
                                    {{ entry.prompt_version?.name }} {{ entry.prompt_version?.version_label }}
                                </div>
                                <div class="mt-1 text-sm text-[var(--muted)]">
                                    {{ entry.usage_notes || 'No additional usage notes.' }}
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
            </section>

            <div v-else class="empty-state">
                No approved prompts match the current search.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
