<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { BookCopy, ClipboardList, FileText, Trash2 } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/formatters';
import { routeWithQuery } from '@/lib/urlState';

const props = defineProps({
    entry: {
        type: Object,
        required: true,
    },
    canManage: {
        type: Boolean,
        required: true,
    },
});

const sourceVersionHref = computed(() =>
    props.entry.prompt_version?.prompt_template_id
        ? routeWithQuery('prompt-templates.show', props.entry.prompt_version.prompt_template_id, {
            tab: 'versions',
            prompt_version_id: props.entry.prompt_version?.id,
        })
        : route('prompt-templates.index'),
);
const approvalHref = computed(() =>
    props.entry.prompt_version?.prompt_template_id
        ? routeWithQuery('prompt-templates.show', props.entry.prompt_version.prompt_template_id, {
            tab: 'library',
            prompt_version_id: props.entry.prompt_version?.id,
        })
        : route('prompt-templates.index'),
);
const runHref = computed(() =>
    routeWithQuery('playground', {}, {
        mode: 'single',
        use_case_id: props.entry.prompt_version?.use_case_id,
        prompt_template_id: props.entry.prompt_version?.prompt_template_id,
        prompt_version_id: props.entry.prompt_version?.id,
        model_name: props.entry.recommended_model || props.entry.prompt_version?.preferred_model || '',
    }),
);

const revokeEntry = () => {
    if (!props.canManage) {
        return;
    }

    const promptLabel = `${props.entry.prompt_version?.name || 'this prompt'} ${props.entry.prompt_version?.version_label || ''}`.trim();

    if (!window.confirm(`Remove ${promptLabel} from the shared library?`)) {
        return;
    }

    router.delete(route('library.destroy', props.entry.id), {
        preserveScroll: false,
    });
};
</script>

<template>
    <Head :title="`${entry.prompt_version?.name || 'Library entry'} ${entry.prompt_version?.version_label || ''}`.trim()" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ entry.prompt_version?.name || 'Approved library entry' }}
                        <span v-if="entry.prompt_version?.version_label">{{ entry.prompt_version.version_label }}</span>
                    </h1>
                    <p class="mt-1 text-sm text-[var(--muted)]">
                        Shared prompt entry ready for controlled reuse across the workspace.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link :href="runHref" class="btn-primary">Run experiments</Link>
                    <Link :href="sourceVersionHref" class="btn-secondary">View prompt version</Link>
                    <Link :href="approvalHref" class="btn-secondary">Open library handoff</Link>
                    <button v-if="canManage" type="button" class="btn-danger" @click="revokeEntry">
                        <Trash2 class="h-4 w-4" />
                        <span>Remove from shared library</span>
                    </button>
                    <Link :href="route('library.index')" class="btn-secondary">Back to library</Link>
                </div>
            </div>
        </template>

        <div class="page-frame-content">
            <section class="panel p-5">
                <PanelHeader
                    title="Library snapshot"
                    description="Key library details and current recommendation metadata."
                    :icon="BookCopy"
                    help="Shows who added the entry, when it happened, which task it belongs to, and what model or usage guidance the team should follow."
                />

                <div class="summary-strip mt-4">
                    <div class="summary-item">
                        <div class="summary-item-label">Task</div>
                        <div class="summary-item-value">{{ entry.prompt_version?.use_case || 'No task' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Recommended model</div>
                        <div class="summary-item-value mono text-xs">{{ entry.recommended_model || 'No override' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Approved by</div>
                        <div class="summary-item-value">{{ entry.approved_by || 'Unknown reviewer' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Approved at</div>
                        <div class="summary-item-value">{{ entry.approved_at ? formatDateTime(entry.approved_at) : 'Not recorded' }}</div>
                    </div>
                </div>
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Usage guidance"
                    description="Operational notes for when this approved prompt should be reused."
                    :icon="ClipboardList"
                    help="Keeps the reuse guidance visible alongside the approved entry so the team can understand intended fit before launching experiments."
                />

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <ClipboardList />
                            <span>Best for</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-[var(--muted)]">
                            {{ entry.best_for || 'General internal use.' }}
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <FileText />
                            <span>Usage notes</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-[var(--muted)]">
                            {{ entry.usage_notes || 'No additional notes were saved for this library entry.' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel p-5">
                <PanelHeader
                    title="Source snapshot"
                    description="Basic context about the prompt version that was approved."
                    :icon="FileText"
                    help="Summarizes the underlying prompt version so reviewers can verify what was approved before jumping into source or experiments."
                />

                <div class="summary-strip mt-4">
                    <div class="summary-item">
                        <div class="summary-item-label">Version</div>
                        <div class="summary-item-value">{{ entry.prompt_version?.version_label || 'Unknown' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Prompt model</div>
                        <div class="summary-item-value mono text-xs">{{ entry.prompt_version?.preferred_model || 'No preference' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-item-label">Approval access</div>
                        <div class="summary-item-value">{{ canManage ? 'Manage' : 'Read only' }}</div>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <ClipboardList />
                            <span>Change summary</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-[var(--muted)]">
                            {{ entry.prompt_version?.change_summary || 'No change summary recorded.' }}
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="text-block-title">
                            <FileText />
                            <span>Version notes</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-[var(--muted)]">
                            {{ entry.prompt_version?.notes || 'No version notes recorded.' }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-3 text-sm">
                    <Link :href="runHref" class="app-inline-link">Run experiments</Link>
                    <Link :href="sourceVersionHref" class="app-inline-link">View prompt version</Link>
                    <Link :href="approvalHref" class="app-inline-link">Open library handoff</Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
