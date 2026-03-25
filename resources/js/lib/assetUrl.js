const normalizePath = (value) => `/${String(value ?? '').replace(/^\/+/, '')}`;

export const resolvePublicAsset = (path) => {
    const normalizedPath = normalizePath(path);

    if (typeof document === 'undefined') {
        return normalizedPath;
    }

    const appUrl = document
        .querySelector('meta[name="app-url"]')
        ?.getAttribute('content')
        ?.trim()
        ?.replace(/\/+$/, '');

    if (! appUrl) {
        return normalizedPath;
    }

    return `${appUrl}${normalizedPath}`;
};
