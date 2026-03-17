<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { BookCopy, Bot, Clock3, FileText, FolderKanban, Search, Target, UserCheck } from 'lucide-vue-next';
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

    return Object.entries(counts)
        .map(([name, count]) => ({ name, count }))
        .sort((left, right) => right.count - left.count);
});
</script>

<template>
    <Head title="Library" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Approved Prompt Library</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">Approved prompt versions for team reuse.</p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Approved entries</div>
                            <div class="summary-item-value">{{ entries.length }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Tasks covered</div>
                            <div class="summary-item-value">{{ groupedCounts.length }}</div>
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

                    <div class="flex flex-wrap gap-3">
                        <Link :href="route('prompt-templates.index')" class="btn-secondary">Review templates</Link>
                        <Link :href="route('playground')" class="btn-primary">Run new experiment</Link>
                    </div>
                </div>
            </section>

            <section class="panel p-4">
                <PanelHeader
                    title="Search approved entries"
                    description="Search by task, prompt name, model, or approval note."
                    :icon="Search"
                />
                <div class="mt-4">
                    <input v-model="search" type="text" class="field-input" placeholder="Task, prompt, model, or note">
                </div>
            </section>

            <section v-if="filteredEntries.length" class="panel p-5">
                <PanelHeader
                    title="Approved entries"
                    description="These prompt versions are already approved for reuse."
                    :icon="BookCopy"
                />

                <div class="mt-4 space-y-4">
                    <div v-for="entry in filteredEntries" :key="entry.id" class="guide-card">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-lg font-black tracking-tight">
                                    {{ entry.prompt_version?.name }} {{ entry.prompt_version?.version_label }}
                                </div>
                                <div class="mt-2 inline-meta">
                                    <span class="inline-meta-item">
                                        <FolderKanban />
                                        {{ entry.prompt_version?.use_case || 'No task' }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right text-sm">
                                <div class="inline-meta-item justify-end">
                                    <Bot />
                                    <span class="mono text-xs">{{ entry.recommended_model || 'No model override' }}</span>
                                </div>
                                <div class="mt-1 inline-meta-item justify-end text-[var(--muted)]">
                                    <Clock3 />
                                    <span>{{ formatDateTime(entry.approved_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="guide-card">
                                <div class="text-block-title">
                                    <Target />
                                    <span>Best for</span>
                                </div>
                                <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                    {{ entry.best_for || 'General internal use' }}
                                </div>
                            </div>
                            <div class="guide-card">
                                <div class="text-block-title">
                                    <UserCheck />
                                    <span>Approved by</span>
                                </div>
                                <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                    {{ entry.approved_by || 'Unknown reviewer' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="label-with-icon">
                                <FileText />
                                <span>Usage notes</span>
                            </div>
                            <div class="guide-card mt-2 text-sm leading-6">
                                {{ entry.usage_notes || 'No additional notes.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div v-else class="empty-state">
                No approved prompts match the current search.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
