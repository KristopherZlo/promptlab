<script setup>
import HelpHint from '@/Components/HelpHint.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    help: {
        type: String,
        default: '',
    },
    icon: {
        type: [Object, Function],
        default: null,
    },
});
</script>

<template>
    <div class="panel-header">
        <div class="panel-header-main">
            <div v-if="icon" class="panel-header-icon">
                <component :is="icon" />
            </div>
            <div class="min-w-0">
                <h2 class="section-title">{{ title }}</h2>
                <p v-if="description" class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ description }}</p>
            </div>
        </div>

        <div class="panel-header-side">
            <slot name="actions" />
            <HelpHint v-if="help" :text="help" :label="`Help for ${title}`" />
        </div>
    </div>
</template>
