<?php

namespace App\Services;

use App\Exceptions\TerminalOperationException;

class ConnectionBaseUrlPolicy
{
    public function allowedBaseUrls(string $driver): array
    {
        return collect(config("llm.connection_base_urls.{$driver}", []))
            ->map(fn ($url) => $this->normalize($url))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function normalize(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $normalizedPath = $path !== '' ? '/'.$path : '';

        return "{$scheme}://{$host}{$port}{$normalizedPath}";
    }

    public function isAllowed(string $driver, ?string $url): bool
    {
        if ($url === null || trim((string) $url) === '') {
            return true;
        }

        $normalized = $this->normalize($url);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, $this->allowedBaseUrls($driver), true);
    }

    public function assertAllowed(string $driver, ?string $url): void
    {
        if ($this->isAllowed($driver, $url)) {
            return;
        }

        throw new TerminalOperationException($this->message($driver));
    }

    public function message(string $driver): string
    {
        $allowed = $this->allowedBaseUrls($driver);

        if ($allowed === []) {
            return 'This provider does not accept custom base URLs in this environment.';
        }

        return 'Use one of the approved base URLs for this provider: '.implode(', ', $allowed).'.';
    }
}
