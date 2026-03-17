import { ref, watch } from 'vue';

const isBlank = (value) => `${value ?? ''}`.trim() === '';

const normalizeValue = (value, defaultValue, allowedValues) => {
    if (isBlank(value)) {
        return defaultValue;
    }

    const nextValue = `${value}`.trim();

    if (allowedValues.length > 0 && !allowedValues.includes(nextValue)) {
        return defaultValue;
    }

    return nextValue;
};

export const useUrlState = ({ key, defaultValue, allowedValues = [], omitIf = defaultValue }) => {
    const initialValue = typeof window === 'undefined'
        ? defaultValue
        : normalizeValue(new URL(window.location.href).searchParams.get(key), defaultValue, allowedValues);
    const state = ref(initialValue);

    watch(state, (value) => {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);
        const nextValue = normalizeValue(value, defaultValue, allowedValues);

        if (nextValue === omitIf || isBlank(nextValue)) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, nextValue);
        }

        const nextUrl = `${url.pathname}${url.search}${url.hash}`;
        window.history.replaceState(window.history.state, '', nextUrl);
    }, { flush: 'post' });

    return state;
};
