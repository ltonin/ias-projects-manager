<?php

declare(strict_types=1);

namespace App\Support;

final class UrlGenerator
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $basePath = '',
        private readonly bool $cleanUrls = true,
    ) {
    }

    /** @param array<string, scalar> $query */
    public function to(string $path = '/', array $query = []): string
    {
        $route = '/' . ltrim($path, '/');
        $base = rtrim($this->baseUrl, '/') . $this->normalizedBasePath();

        if (!$this->cleanUrls) {
            $query = ['route' => trim($route, '/')] + $query;
            return $base . '/index.php?' . http_build_query($query);
        }

        $url = $base . ($route === '/' ? '/' : $route);
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    public function asset(string $path): string
    {
        return rtrim($this->baseUrl, '/') . $this->normalizedBasePath() . '/assets/' . ltrim($path, '/');
    }

    private function normalizedBasePath(): string
    {
        $path = trim($this->basePath);
        return $path === '' || $path === '/' ? '' : '/' . trim($path, '/');
    }
}
