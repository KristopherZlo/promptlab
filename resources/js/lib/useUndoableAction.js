import { onBeforeUnmount, reactive } from 'vue';

export const useUndoableAction = (defaultDelayMs = 6000) => {
    const pendingAction = reactive({
        active: false,
        label: '',
        secondsRemaining: 0,
        busy: false,
    });

    let commitCallback = null;
    let timeoutHandle = null;
    let intervalHandle = null;
    let deadline = null;

    const clearTimers = () => {
        if (timeoutHandle) {
            window.clearTimeout(timeoutHandle);
            timeoutHandle = null;
        }

        if (intervalHandle) {
            window.clearInterval(intervalHandle);
            intervalHandle = null;
        }
    };

    const reset = () => {
        clearTimers();
        commitCallback = null;
        deadline = null;
        pendingAction.active = false;
        pendingAction.label = '';
        pendingAction.secondsRemaining = 0;
        pendingAction.busy = false;
    };

    const updateCountdown = () => {
        if (!deadline) {
            pendingAction.secondsRemaining = 0;
            return;
        }

        pendingAction.secondsRemaining = Math.max(1, Math.ceil((deadline - Date.now()) / 1000));
    };

    const commit = async () => {
        if (!pendingAction.active || pendingAction.busy || typeof commitCallback !== 'function') {
            return;
        }

        clearTimers();
        pendingAction.busy = true;

        const action = commitCallback;

        try {
            await action();
        } finally {
            reset();
        }
    };

    const schedule = ({ label, onCommit, delayMs = defaultDelayMs }) => {
        reset();
        commitCallback = onCommit;
        deadline = Date.now() + delayMs;

        pendingAction.active = true;
        pendingAction.label = label;
        pendingAction.busy = false;
        updateCountdown();

        intervalHandle = window.setInterval(updateCountdown, 250);
        timeoutHandle = window.setTimeout(commit, delayMs);
    };

    const cancel = () => {
        reset();
    };

    onBeforeUnmount(reset);

    return {
        pendingAction,
        scheduleAction: schedule,
        commitAction: commit,
        cancelAction: cancel,
    };
};
