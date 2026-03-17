<script setup>
const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    tabs: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const selectTab = (tab) => {
    if (tab.disabled || tab.key === props.modelValue) {
        return;
    }

    emit('update:modelValue', tab.key);
};
</script>

<template>
    <div class="workspace-tabs" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="workspace-tab"
            :class="{
                'workspace-tab-active': tab.key === modelValue,
                'workspace-tab-disabled': tab.disabled,
            }"
            :disabled="tab.disabled"
            @click="selectTab(tab)"
        >
            <div class="flex items-start gap-3">
                <component v-if="tab.icon" :is="tab.icon" class="workspace-tab-icon" />
                <div class="min-w-0 flex-1 text-left">
                    <div class="workspace-tab-label-row">
                        <span class="workspace-tab-label">{{ tab.label }}</span>
                        <span v-if="tab.badge" class="status-chip">{{ tab.badge }}</span>
                    </div>
                    <div v-if="tab.caption" class="workspace-tab-caption">{{ tab.caption }}</div>
                </div>
            </div>
        </button>
    </div>
</template>
