const THEME_STORAGE_KEY = 'promptlab-theme-mode';
const THEME_MEDIA_QUERY = '(prefers-color-scheme: dark)';
const THEME_MODES = ['system', 'light', 'dark'];

const normalizeThemeMode = (value) => (THEME_MODES.includes(value) ? value : 'system');

const systemPrefersDark = () => window.matchMedia?.(THEME_MEDIA_QUERY)?.matches ?? false;

export const readThemeMode = () => {
    try {
        return normalizeThemeMode(window.localStorage.getItem(THEME_STORAGE_KEY));
    } catch (error) {
        return 'system';
    }
};

export const resolveTheme = (mode) => {
    const normalized = normalizeThemeMode(mode);

    if (normalized === 'light' || normalized === 'dark') {
        return normalized;
    }

    return systemPrefersDark() ? 'dark' : 'light';
};

export const applyThemeMode = (mode) => {
    const normalized = normalizeThemeMode(mode);
    const resolved = resolveTheme(normalized);
    const root = document.documentElement;
    const body = document.body;
    const background = resolved === 'dark' ? '#131313' : '#ebebeb';

    root.dataset.theme = resolved;
    root.dataset.themeMode = normalized;
    root.style.backgroundColor = background;

    if (body) {
        body.dataset.theme = resolved;
        body.dataset.themeMode = normalized;
        body.style.backgroundColor = background;
    }

    try {
        window.localStorage.setItem(THEME_STORAGE_KEY, normalized);
    } catch (error) {
        // Ignore storage failures in restricted environments.
    }

    return resolved;
};

export const onSystemThemeChange = (callback) => {
    const media = window.matchMedia?.(THEME_MEDIA_QUERY);

    if (! media) {
        return () => {};
    }

    const handler = () => callback(systemPrefersDark() ? 'dark' : 'light');

    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', handler);

        return () => media.removeEventListener('change', handler);
    }

    media.addListener(handler);

    return () => media.removeListener(handler);
};
