const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export const formatDateTime = (value, fallback = 'N/A') => {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    return dateTimeFormatter.format(date);
};

export const formatScore = (value, fallback = 'N/A') => {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    const normalized = Number(value);

    return Number.isNaN(normalized) ? fallback : normalized.toFixed(1);
};

export const safeJsonStringify = (value, fallback = '') => {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return fallback;
    }
};

export const parseJsonInput = (value, emptyValue) => {
    const source = `${value ?? ''}`.trim();

    if (!source) {
        return {
            valid: true,
            value: emptyValue,
        };
    }

    try {
        return {
            valid: true,
            value: JSON.parse(source),
        };
    } catch (error) {
        return {
            valid: false,
            value: emptyValue,
            error: error instanceof Error ? error.message : 'Invalid JSON payload.',
        };
    }
};

export const parseTagList = (value) =>
    `${value ?? ''}`
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);

export const truncateText = (value, limit = 180) => {
    const normalized = `${value ?? ''}`.replace(/\s+/g, ' ').trim();

    if (normalized.length <= limit) {
        return normalized;
    }

    return `${normalized.slice(0, Math.max(limit - 3, 1)).trimEnd()}...`;
};

export const replacePromptVariables = (template, variables) =>
    `${template ?? ''}`.replace(/{{\s*([a-zA-Z0-9_.]+)\s*}}/g, (_, key) => {
        const value = key
            .split('.')
            .reduce((carry, segment) => (carry == null ? undefined : carry[segment]), variables);

        if (Array.isArray(value) || (value && typeof value === 'object')) {
            return safeJsonStringify(value, '');
        }

        return value == null ? '' : `${value}`;
    });

export const buildPromptPreview = (version, inputText, variables = {}) => {
    if (!version) {
        return '';
    }

    const payload = {
        input_text: `${inputText ?? ''}`.trim(),
        ...variables,
    };

    const systemPrompt = replacePromptVariables(version.system_prompt ?? '', payload).trim();
    const userPrompt = replacePromptVariables(version.user_prompt_template ?? '', payload).trim();

    return [
        systemPrompt ? `SYSTEM:\n${systemPrompt}` : null,
        `USER:\n${userPrompt}`,
    ]
        .filter(Boolean)
        .join('\n\n')
        .trim();
};
