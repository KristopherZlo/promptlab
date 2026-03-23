<script setup>
import { watch } from 'vue';
import { inferToastTone, pushToast } from '@/lib/toasts';

const props = defineProps({
    message: {
        type: String,
        default: '',
    },
    tone: {
        type: String,
        default: 'auto',
    },
    actionHref: {
        type: String,
        default: '',
    },
    actionLabel: {
        type: String,
        default: '',
    },
    duration: {
        type: Number,
        default: 5200,
    },
});

let lastSignature = '';

watch(
    () => [props.message, props.tone, props.actionHref, props.actionLabel, props.duration],
    ([message, tone, actionHref, actionLabel, duration]) => {
        const normalizedMessage = `${message ?? ''}`.trim();

        if (normalizedMessage === '') {
            lastSignature = '';
            return;
        }

        const signature = [
            normalizedMessage,
            tone || 'auto',
            actionHref || '',
            actionLabel || '',
            duration,
        ].join('::');

        if (signature === lastSignature) {
            return;
        }

        pushToast({
            message: normalizedMessage,
            tone: tone === 'auto' ? inferToastTone(normalizedMessage) : tone,
            actionHref: actionHref || null,
            actionLabel: actionLabel || null,
            duration,
        });

        lastSignature = signature;
    },
    { immediate: true },
);
</script>

<template />
