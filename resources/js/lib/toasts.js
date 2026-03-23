import { reactive } from 'vue';

const DEFAULT_DURATION = 5200;
const MAX_TOASTS = 5;
const timers = new Map();

export const toastState = reactive({
    items: [],
});

let nextToastId = 1;

export const inferToastTone = (message) => {
    const value = `${message ?? ''}`.trim();

    if (value === '') {
        return 'info';
    }

    if (/(failed|could not|error|invalid|missing|unable|fix|denied|required|forbidden)/i.test(value)) {
        return 'error';
    }

    if (/(saved|created|updated|removed|copied|loaded|imported|selected|verified|added|deleted|archived|revoked|sent|switched)/i.test(value)) {
        return 'success';
    }

    return 'info';
};

const clearTimer = (id) => {
    const timer = timers.get(id);

    if (timer) {
        window.clearTimeout(timer);
        timers.delete(id);
    }
};

export const dismissToast = (id) => {
    clearTimer(id);

    const index = toastState.items.findIndex((toast) => toast.id === id);

    if (index !== -1) {
        toastState.items.splice(index, 1);
    }
};

export const pushToast = ({
    message,
    tone = 'info',
    duration = DEFAULT_DURATION,
    actionHref = null,
    actionLabel = null,
} = {}) => {
    const normalizedMessage = `${message ?? ''}`.trim();

    if (normalizedMessage === '') {
        return null;
    }

    const id = nextToastId++;

    toastState.items.push({
        id,
        message: normalizedMessage,
        tone,
        actionHref,
        actionLabel,
    });

    while (toastState.items.length > MAX_TOASTS) {
        dismissToast(toastState.items[0].id);
    }

    if (typeof window !== 'undefined' && duration > 0) {
        timers.set(id, window.setTimeout(() => dismissToast(id), duration));
    }

    return id;
};

export const useToastStore = () => ({
    toasts: toastState.items,
    pushToast,
    dismissToast,
});
