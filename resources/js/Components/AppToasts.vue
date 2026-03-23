<script setup>
import { computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Info, X } from 'lucide-vue-next';
import { dismissToast, pushToast, useToastStore } from '@/lib/toasts';

const { toasts } = useToastStore();
const page = usePage();

const toneMeta = {
    success: {
        icon: CheckCircle2,
        label: 'Success',
    },
    error: {
        icon: AlertCircle,
        label: 'Error',
    },
    info: {
        icon: Info,
        label: 'Notice',
    },
};

let lastFlashSuccess = '';

watch(
    () => page.props.flash?.success,
    (message) => {
        const normalizedMessage = `${message ?? ''}`.trim();

        if (normalizedMessage === '') {
            lastFlashSuccess = '';
            return;
        }

        if (normalizedMessage === lastFlashSuccess) {
            return;
        }

        pushToast({
            message: normalizedMessage,
            tone: 'success',
        });

        lastFlashSuccess = normalizedMessage;
    },
    { immediate: true },
);

const toastEntries = computed(() => [...toasts]);
const iconFor = (tone) => toneMeta[tone]?.icon ?? toneMeta.info.icon;
const labelFor = (tone) => toneMeta[tone]?.label ?? toneMeta.info.label;
</script>

<template>
    <Teleport to="body">
        <div class="app-toast-stack" aria-live="polite" aria-atomic="true">
            <TransitionGroup name="app-toast-list">
                <article
                    v-for="toast in toastEntries"
                    :key="toast.id"
                    class="app-toast"
                    :class="`app-toast-${toast.tone}`"
                >
                    <div class="app-toast-icon">
                        <component :is="iconFor(toast.tone)" class="h-4 w-4" />
                    </div>

                    <div class="app-toast-copy">
                        <div class="app-toast-label">{{ labelFor(toast.tone) }}</div>
                        <div class="app-toast-message">{{ toast.message }}</div>

                        <div v-if="toast.actionHref && toast.actionLabel" class="app-toast-actions">
                            <Link :href="toast.actionHref" class="app-toast-action" @click="dismissToast(toast.id)">
                                {{ toast.actionLabel }}
                            </Link>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="app-toast-dismiss"
                        aria-label="Dismiss notification"
                        @click="dismissToast(toast.id)"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
