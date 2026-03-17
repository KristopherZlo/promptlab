<script setup>
import HelpHint from '@/Components/HelpHint.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    summary: {
        type: String,
        required: true,
    },
    cards: {
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
    <section class="page-primer">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <div class="flex items-start gap-2">
                    <h2 class="section-title">{{ title }}</h2>
                    <HelpHint v-if="help" :text="help" :label="`Help for ${title}`" />
                </div>
                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ summary }}</p>
            </div>
            <div v-if="$slots.actions" class="shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="cards.length" class="page-primer-grid">
            <div v-for="card in cards" :key="card.title" class="guide-card">
                <div class="font-bold">{{ card.title }}</div>
                <div class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ card.body }}</div>
            </div>
        </div>
    </section>
</template>
