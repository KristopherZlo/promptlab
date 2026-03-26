<script setup>
import { computed } from 'vue';
import { BadgeCheck, ScanSearch, TriangleAlert } from 'lucide-vue-next';
import { safeJsonStringify, truncateText } from '@/lib/formatters';

const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
});

const automaticEvaluation = computed(() => props.run.automatic_evaluation ?? {
    configured: false,
    checks: [],
});

const previewValue = (value) => {
    if (value == null || value === '') {
        return 'Not available';
    }

    if (typeof value === 'string') {
        return truncateText(value, 180);
    }

    return truncateText(safeJsonStringify(value, '{}'), 180);
};
</script>

<template>
    <div v-if="automaticEvaluation.configured" class="mt-4">
        <div class="label-with-icon">
            <ScanSearch />
            <span>Quality checks</span>
        </div>

        <div class="mt-2 grid gap-3 xl:grid-cols-[220px_minmax(0,1fr)]">
            <div class="guide-card">
                <div class="inline-meta-item text-xs text-[var(--muted)]">
                    <BadgeCheck />
                    <span>Overall result</span>
                </div>
                <div class="mt-1 font-bold">
                    {{ automaticEvaluation.passed ? 'Passed' : 'Needs attention' }}
                </div>
                <div class="mt-2 text-sm text-[var(--muted)]">
                    {{ automaticEvaluation.passed_checks }}/{{ automaticEvaluation.total_checks }} checks matched
                </div>
                <div class="mt-3 text-xs leading-5 text-[var(--muted)]">
                    {{ automaticEvaluation.summary }}
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="check in automaticEvaluation.checks"
                    :key="check.key"
                    class="guide-card border"
                    :class="check.passed
                        ? 'border-[var(--success)]/20 bg-[rgba(125,164,138,0.08)]'
                        : 'border-[var(--danger)]/20 bg-[rgba(224,30,90,0.08)]'"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="font-bold">{{ check.label }}</div>
                        <span class="text-xs font-semibold" :class="check.passed ? 'text-[var(--success)]' : 'text-[var(--danger)]'">
                            {{ check.passed ? 'Pass' : 'Check again' }}
                        </span>
                    </div>

                    <div class="mt-3 text-xs uppercase tracking-[0.14em] text-[var(--muted)]">Expected result</div>
                    <pre class="code-block mt-2 text-xs">{{ previewValue(check.expected_preview) }}</pre>

                    <div class="mt-3 text-xs uppercase tracking-[0.14em] text-[var(--muted)]">Actual result</div>
                    <pre class="code-block mt-2 text-xs">{{ previewValue(check.actual_preview) }}</pre>

                    <div class="mt-3 flex items-start gap-2 text-xs leading-5" :class="check.passed ? 'text-[var(--muted)]' : 'text-[var(--danger)]'">
                        <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>{{ check.message }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
