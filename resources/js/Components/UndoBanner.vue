<script setup>
import { RotateCcw, Trash2 } from 'lucide-vue-next';

defineProps({
    label: {
        type: String,
        required: true,
    },
    secondsRemaining: {
        type: Number,
        default: 0,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['undo', 'commit']);
</script>

<template>
    <div class="notice-banner flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-start gap-3">
            <Trash2 class="mt-0.5 h-4 w-4 shrink-0 text-[var(--danger)]" />
            <div>
                <div class="font-bold">Deletion scheduled</div>
                <div class="mt-1 text-sm text-[var(--muted)]">
                    {{ label }}
                    <span v-if="secondsRemaining > 0">Auto-delete in {{ secondsRemaining }}s.</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-secondary" @click="$emit('undo')">
                <RotateCcw class="mr-2 h-4 w-4" />
                Undo
            </button>
            <button type="button" class="btn-danger" :disabled="busy" @click="$emit('commit')">
                <Trash2 class="h-4 w-4" />
                {{ busy ? 'Deleting...' : 'Delete now' }}
            </button>
        </div>
    </div>
</template>
