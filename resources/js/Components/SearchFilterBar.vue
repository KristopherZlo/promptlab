<script setup>
import { Search } from 'lucide-vue-next';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="search-filter-bar">
        <label class="search-filter-input">
            <Search class="search-filter-input-icon" />
            <input
                :value="modelValue"
                type="text"
                class="search-filter-input-control"
                :placeholder="placeholder"
                @input="emit('update:modelValue', $event.target.value)"
            >
        </label>

        <div class="search-filter-actions">
            <slot />
        </div>
    </div>
</template>
