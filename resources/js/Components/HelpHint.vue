<script setup>
import { CircleQuestionMark } from 'lucide-vue-next';

defineProps({
    text: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: 'Open help',
    },
});
</script>

<template>
    <div class="help-hint">
        <button type="button" class="help-hint-button" :aria-label="label">
            <CircleQuestionMark />
        </button>
        <div class="help-hint-tooltip">
            {{ text }}
        </div>
    </div>
</template>
