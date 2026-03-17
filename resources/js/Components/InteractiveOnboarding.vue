<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    steps: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const tooltipRef = ref(null);
const stepIndex = ref(0);
const spotlightStyle = ref({ opacity: 0 });
const tooltipStyle = ref({ opacity: 0 });

const isOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const totalSteps = computed(() => props.steps.length);
const currentStep = computed(() => props.steps[stepIndex.value] ?? null);
const isFirstStep = computed(() => stepIndex.value === 0);
const isLastStep = computed(() => stepIndex.value === Math.max(totalSteps.value - 1, 0));

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const resolveTarget = (step) => {
    const selectors = Array.isArray(step?.selectors)
        ? step.selectors
        : [step?.selector].filter(Boolean);

    for (const selector of selectors) {
        const element = document.querySelector(selector);

        if (element && element.getClientRects().length > 0) {
            return element;
        }
    }

    return null;
};

const positionTour = async () => {
    if (!isOpen.value || !currentStep.value) {
        return;
    }

    await nextTick();

    const target = resolveTarget(currentStep.value);

    if (!target) {
        spotlightStyle.value = { opacity: 0 };
        tooltipStyle.value = {
            opacity: 1,
            top: '24px',
            right: '24px',
        };

        return;
    }

    target.scrollIntoView({
        block: 'center',
        inline: 'nearest',
    });

    await new Promise((resolve) => requestAnimationFrame(resolve));

    const rect = target.getBoundingClientRect();
    const padding = 8;
    const focusTop = clamp(rect.top - padding, 8, window.innerHeight - 40);
    const focusLeft = clamp(rect.left - padding, 8, window.innerWidth - 40);
    const focusWidth = Math.min(rect.width + padding * 2, window.innerWidth - focusLeft - 8);
    const focusHeight = Math.min(rect.height + padding * 2, window.innerHeight - focusTop - 8);

    spotlightStyle.value = {
        opacity: 1,
        top: `${focusTop}px`,
        left: `${focusLeft}px`,
        width: `${focusWidth}px`,
        height: `${focusHeight}px`,
    };

    await nextTick();

    const tooltipWidth = tooltipRef.value?.offsetWidth ?? 320;
    const tooltipHeight = tooltipRef.value?.offsetHeight ?? 188;
    const gap = 16;
    const margin = 16;

    let top = clamp(rect.top, margin, window.innerHeight - tooltipHeight - margin);
    let left = rect.right + gap;

    if (rect.right + gap + tooltipWidth > window.innerWidth - margin) {
        left = rect.left - tooltipWidth - gap;
    }

    if (left < margin) {
        left = clamp(rect.left, margin, window.innerWidth - tooltipWidth - margin);
        top = rect.bottom + gap;
    }

    if (top + tooltipHeight > window.innerHeight - margin) {
        top = rect.top - tooltipHeight - gap;
    }

    if (top < margin) {
        top = clamp(window.innerHeight - tooltipHeight - margin, margin, window.innerHeight - tooltipHeight - margin);
    }

    tooltipStyle.value = {
        opacity: 1,
        top: `${top}px`,
        left: `${left}px`,
    };
};

const close = () => {
    isOpen.value = false;
};

const nextStep = () => {
    if (isLastStep.value) {
        close();
        return;
    }

    stepIndex.value += 1;
};

const previousStep = () => {
    if (isFirstStep.value) {
        return;
    }

    stepIndex.value -= 1;
};

const handleKeydown = (event) => {
    if (!isOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        close();
    }

    if (event.key === 'ArrowRight') {
        nextStep();
    }

    if (event.key === 'ArrowLeft') {
        previousStep();
    }
};

const handleViewportChange = () => {
    if (isOpen.value) {
        positionTour();
    }
};

watch(isOpen, async (open) => {
    if (!open) {
        spotlightStyle.value = { opacity: 0 };
        tooltipStyle.value = { opacity: 0 };
        return;
    }

    stepIndex.value = 0;
    await positionTour();
});

watch(stepIndex, async () => {
    if (isOpen.value) {
        await positionTour();
    }
});

watch(
    () => props.steps,
    async () => {
        if (isOpen.value) {
            stepIndex.value = 0;
            await positionTour();
        }
    },
    { deep: true },
);

onMounted(() => {
    window.addEventListener('resize', handleViewportChange);
    window.addEventListener('scroll', handleViewportChange, true);
    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleViewportChange);
    window.removeEventListener('scroll', handleViewportChange, true);
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen && currentStep" class="tour-layer">
            <button type="button" class="tour-scrim" aria-label="Close tutorial" @click="close" />

            <div class="tour-spotlight" :style="spotlightStyle" />

            <section ref="tooltipRef" class="tour-card" :style="tooltipStyle" aria-live="polite">
                <div class="tour-card-header">
                    <div class="text-sm font-bold">{{ stepIndex + 1 }} of {{ totalSteps }}</div>
                    <button type="button" class="help-hint-button" aria-label="Close tutorial" @click="close">
                        <X />
                    </button>
                </div>

                <div class="mt-3 text-lg font-black tracking-tight">{{ currentStep.title }}</div>
                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ currentStep.body }}</p>

                <div class="tour-card-actions">
                    <button type="button" class="btn-secondary" :disabled="isFirstStep" @click="previousStep">
                        <ChevronLeft class="h-4 w-4" />
                        Back
                    </button>

                    <button type="button" class="btn-primary" @click="nextStep">
                        {{ isLastStep ? 'Done' : 'Next' }}
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
