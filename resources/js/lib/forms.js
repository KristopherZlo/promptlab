export const applyServerErrors = (form, error) => {
    const errors = error.response?.data?.errors ?? {};

    if (typeof form.clearErrors === 'function') {
        form.clearErrors();
    }

    Object.entries(errors).forEach(([key, value]) => {
        if (typeof form.setError === 'function') {
            form.setError(key, Array.isArray(value) ? value[0] : value);
        }
    });

    return errors;
};

export const extractServerMessage = (error, fallback = 'Request failed.') => {
    const errors = error.response?.data?.errors ?? {};
    const firstFieldError = Object.values(errors)[0];

    if (Array.isArray(firstFieldError) && firstFieldError[0]) {
        return firstFieldError[0];
    }

    if (typeof firstFieldError === 'string' && firstFieldError) {
        return firstFieldError;
    }

    return error.response?.data?.message || fallback;
};

export const formatRoleLabel = (value) =>
    `${value ?? ''}`
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
