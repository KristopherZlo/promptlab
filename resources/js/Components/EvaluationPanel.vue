<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatDateTime, formatScore } from '@/lib/formatters';

const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['saved']);

const page = usePage();
const saving = ref(false);
const saved = ref(false);
const errors = reactive({});
const evaluations = computed(() => props.run.evaluations ?? []);

const currentEvaluation = computed(() =>
    evaluations.value.find((evaluation) => evaluation.evaluator_id === page.props.auth.user?.id) ?? null,
);
const sortedEvaluations = computed(() =>
    [...evaluations.value].sort((left, right) =>
        new Date(right.updated_at ?? right.created_at ?? 0).getTime() - new Date(left.updated_at ?? left.created_at ?? 0).getTime(),
    ),
);
const teamReviewSummary = computed(() => {
    const scoreValues = evaluations.value
        .map((evaluation) => evaluation.average_score)
        .filter((score) => score != null);
    const formatVotes = evaluations.value.filter((evaluation) => evaluation.format_valid_manual === true).length;
    const latestEvaluation = sortedEvaluations.value[0] ?? null;

    return {
        count: evaluations.value.length,
        averageScore: scoreValues.length > 0
            ? scoreValues.reduce((total, score) => total + Number(score), 0) / scoreValues.length
            : null,
        formatVotes,
        latestEvaluation,
    };
});
const isReviewable = computed(() => props.run.is_reviewable ?? ['success', 'invalid_format'].includes(props.run.status));
const reviewBlocker = computed(() => {
    switch (props.run.status) {
    case 'queued':
        return {
            title: 'Manual review unlocks after execution starts',
            description: 'This run has not produced an output yet. Wait until the queue worker picks it up and the run finishes before saving reviewer scores.',
        };
    case 'running':
        return {
            title: 'Manual review is temporarily locked',
            description: 'This run is still executing. Reviewer scores should be saved only after the final output appears on the page.',
        };
    case 'failed':
        return {
            title: 'Manual review is unavailable for failed runs',
            description: 'This run stopped before it produced a reviewable output. Use the runtime error and experiment status to diagnose the failure first.',
        };
    default:
        return {
            title: 'Manual review is not available yet',
            description: 'This run is not in a reviewable state.',
        };
    }
});

const defaultFormState = () => ({
    clarity_score: currentEvaluation.value?.clarity_score ?? '',
    correctness_score: currentEvaluation.value?.correctness_score ?? '',
    completeness_score: currentEvaluation.value?.completeness_score ?? '',
    tone_score: currentEvaluation.value?.tone_score ?? '',
    format_valid_manual: currentEvaluation.value?.format_valid_manual ?? props.run.format_valid ?? false,
    hallucination_risk: currentEvaluation.value?.hallucination_risk ?? 'low',
    notes: currentEvaluation.value?.notes ?? '',
});

const form = reactive(defaultFormState());

const replaceFormState = () => {
    Object.assign(form, defaultFormState());
};

watch(currentEvaluation, replaceFormState, { immediate: true });

const submit = async () => {
    saving.value = true;
    saved.value = false;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        await axios.post(route('api.evaluations.store'), {
            experiment_run_id: props.run.id,
            clarity_score: form.clarity_score || null,
            correctness_score: form.correctness_score || null,
            completeness_score: form.completeness_score || null,
            tone_score: form.tone_score || null,
            format_valid_manual: Boolean(form.format_valid_manual),
            hallucination_risk: form.hallucination_risk || null,
            notes: form.notes || null,
        });

        saved.value = true;
        emit('saved');
    } catch (error) {
        const serverErrors = error.response?.data?.errors ?? {};

        Object.entries(serverErrors).forEach(([key, messages]) => {
            errors[key] = Array.isArray(messages) ? messages[0] : messages;
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="panel-muted p-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-medium">Manual evaluation</h3>
                <p class="mt-1 text-xs text-[var(--muted)]">
                    Save a reviewer score for clarity, correctness, completeness, tone, and format.
                </p>
            </div>
            <div v-if="saved" class="text-xs text-[var(--success)]">Saved</div>
        </div>

        <div v-if="teamReviewSummary.count" class="summary-strip mt-4">
            <div class="summary-item">
                <div class="summary-item-label">Reviews</div>
                <div class="summary-item-value">{{ teamReviewSummary.count }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-item-label">Average score</div>
                <div class="summary-item-value">{{ formatScore(teamReviewSummary.averageScore) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-item-label">Format confirmed</div>
                <div class="summary-item-value">{{ teamReviewSummary.formatVotes }}/{{ teamReviewSummary.count }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-item-label">Latest review</div>
                <div class="summary-item-value">
                    {{ teamReviewSummary.latestEvaluation?.evaluator_name || 'Unknown reviewer' }}
                    <span v-if="teamReviewSummary.latestEvaluation?.updated_at" class="text-[var(--muted)]">
                        · {{ formatDateTime(teamReviewSummary.latestEvaluation.updated_at) }}
                    </span>
                </div>
            </div>
        </div>
        <div v-else class="mt-4 text-sm text-[var(--muted)]">
            {{ isReviewable ? 'No team reviews saved for this run yet.' : reviewBlocker.description }}
        </div>

        <div v-if="sortedEvaluations.length" class="mt-4 space-y-3">
            <div class="text-sm font-medium">Team review history</div>

            <div
                v-for="evaluation in sortedEvaluations"
                :key="evaluation.id"
                class="guide-card"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">
                            {{ evaluation.evaluator_name || 'Unknown reviewer' }}
                            <span v-if="evaluation.evaluator_id === page.props.auth.user?.id" class="text-[var(--muted)]">(You)</span>
                        </div>
                        <div class="mt-1 text-xs text-[var(--muted)]">
                            {{ evaluation.updated_at ? formatDateTime(evaluation.updated_at) : 'No review date' }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-medium">{{ formatScore(evaluation.average_score) }}</div>
                        <div class="mt-1 text-xs text-[var(--muted)]">{{ evaluation.hallucination_risk || 'Risk not set' }}</div>
                    </div>
                </div>

                <div class="summary-list mt-4">
                    <div class="summary-row">
                        <span>Clarity</span>
                        <span>{{ evaluation.clarity_score ?? 'Not scored' }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Correctness</span>
                        <span>{{ evaluation.correctness_score ?? 'Not scored' }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Completeness</span>
                        <span>{{ evaluation.completeness_score ?? 'Not scored' }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Tone</span>
                        <span>{{ evaluation.tone_score ?? 'Not scored' }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Format</span>
                        <span>{{ evaluation.format_valid_manual ? 'Valid' : 'Invalid' }}</span>
                    </div>
                </div>

                <div v-if="evaluation.notes" class="mt-4 text-sm leading-6 text-[var(--muted)]">
                    {{ evaluation.notes }}
                </div>
            </div>
        </div>

        <div v-if="isReviewable">
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="field-label">Clarity</label>
                    <select v-model="form.clarity_score" class="field-select">
                        <option value="">Not scored</option>
                        <option v-for="score in 5" :key="`clarity-${score}`" :value="score">{{ score }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Correctness</label>
                    <select v-model="form.correctness_score" class="field-select">
                        <option value="">Not scored</option>
                        <option v-for="score in 5" :key="`correctness-${score}`" :value="score">{{ score }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Completeness</label>
                    <select v-model="form.completeness_score" class="field-select">
                        <option value="">Not scored</option>
                        <option v-for="score in 5" :key="`completeness-${score}`" :value="score">{{ score }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Tone</label>
                    <select v-model="form.tone_score" class="field-select">
                        <option value="">Not scored</option>
                        <option v-for="score in 5" :key="`tone-${score}`" :value="score">{{ score }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Hallucination risk</label>
                    <select v-model="form.hallucination_risk" class="field-select">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <label class="check-row">
                    <input v-model="form.format_valid_manual" type="checkbox">
                    <div>
                        <div class="text-sm font-medium">Format valid</div>
                        <div class="mt-1 text-xs text-[var(--muted)]">Reviewer confirms that the output follows the expected format.</div>
                    </div>
                </label>
            </div>

            <div class="mt-3">
                <label class="field-label">Notes</label>
                <textarea v-model="form.notes" class="field-textarea"></textarea>
                <div v-if="errors.experiment_run_id" class="field-error">{{ errors.experiment_run_id }}</div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-4">
                <div class="text-xs text-[var(--muted)]">
                    Existing evaluations: {{ evaluations.length }}
                </div>
                <button type="button" class="btn-secondary" :disabled="saving" @click="submit">
                    {{ saving ? 'Saving...' : 'Save evaluation' }}
                </button>
            </div>
        </div>
        <div v-else class="guide-card mt-4">
            <div class="font-medium">{{ reviewBlocker.title }}</div>
            <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                {{ reviewBlocker.description }}
            </div>
        </div>
    </div>
</template>
