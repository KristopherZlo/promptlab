<script setup>
import { Link } from '@inertiajs/vue3';
import HelpHint from '@/Components/HelpHint.vue';

defineProps({
    title: {
        type: String,
        default: 'Team workflow',
    },
    description: {
        type: String,
        default: '',
    },
    steps: {
        type: Array,
        default: () => [],
    },
    help: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="workflow-strip">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <div class="section-kicker">Workflow</div>
                <div class="mt-1 flex items-start gap-2">
                    <h2 class="section-title">{{ title }}</h2>
                    <HelpHint v-if="help" :text="help" :label="`Help for ${title}`" />
                </div>
                <p v-if="description" class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="steps.length" class="workflow-strip-grid">
            <component
                :is="step.route ? Link : 'div'"
                v-for="(step, index) in steps"
                :key="step.id || step.title || index"
                v-bind="step.route ? { href: route(step.route) } : {}"
                class="workflow-step"
                :class="{
                    'workflow-step-current': step.status === 'current',
                    'workflow-step-complete': step.status === 'complete',
                }"
            >
                <div class="workflow-step-topline">
                    <span class="workflow-step-index">{{ step.step || `Step ${index + 1}` }}</span>
                    <span v-if="step.badge" class="status-chip">{{ step.badge }}</span>
                </div>

                <div class="mt-3 flex items-start gap-3">
                    <component v-if="step.icon" :is="step.icon" class="workflow-step-icon" />
                    <div class="min-w-0">
                        <div class="font-bold">{{ step.title }}</div>
                        <div v-if="step.description" class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            {{ step.description }}
                        </div>
                    </div>
                </div>

                <div v-if="step.meta" class="workflow-step-meta">
                    {{ step.meta }}
                </div>
            </component>
        </div>
    </section>
</template>
