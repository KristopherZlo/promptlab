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
const teamReviewSummary = computed(() => {
    const scoreValues = evaluations.value
        .map((evaluation) => evaluation.average_score)
        .filter((score) => score != null);
    const formatVotes = evaluations.value.filter((evaluation) => evaluation.format_valid_manual === true).length;
    const sortedEvaluations = [...evaluations.value].sort((left, right) =>
        new Date(right.updated_at ?? right.created_at ?? 0).getTime() - new Date(left.updated_at ?? left.created_at ?? 0).getTime(),
    );
    const latestEvaluation = sortedEvaluations[0] ?? null;

    return {
        count: evaluations.value.length,
        averageScore: scoreValues.length > 0
            ? scoreValues.reduce((total, score) => total + Number(score), 0) / scoreValues.length
            : null,
        formatVotes,
        latestEvaluation,
    };
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
            No team reviews saved for this run yet.
        </div>

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
</template>
