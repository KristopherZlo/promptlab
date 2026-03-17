<script setup>
import { computed } from 'vue';
import { formatPlatformRoleLabel, formatRoleLabel } from '@/lib/forms';

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'team',
    },
});

const label = computed(() => (
    props.tone === 'platform'
        ? formatPlatformRoleLabel(props.value)
        : formatRoleLabel(props.value)
));
</script>

<template>
    <span class="role-badge" :class="`role-badge-${tone}`">
        {{ label }}
    </span>
</template>
