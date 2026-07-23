<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string, mixed> $query @param array<string, mixed> $post @param array<string, mixed> $server */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $server = [],
    ) {
    }

    /** @param array<string, mixed> $server @param array<string, mixed> $query @param array<string, mixed> $post */
    public static function fromGlobals(array $server, array $query, array $post, string $basePath = ''): self
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $route = $query['route'] ?? null;
        if (is_string($route)) {
            $path = '/' . ltrim($route, '/');
        } else {
            $path = (string) parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $normalizedBase = '/' . trim($basePath, '/');
            if ($normalizedBase !== '/' && ($path === $normalizedBase || str_starts_with($path, $normalizedBase . '/'))) {
                $path = substr($path, strlen($normalizedBase)) ?: '/';
            }
            if ($path === '/index.php') {
                $path = '/';
            }
        }

        return new self($method, '/' . ltrim($path, '/'), $query, $post, $server);
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    /** @return array<string, mixed> */
    public function queryData(): array { return $this->query; }
    public function post(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $default; }
    /** @return array<string, mixed> */
    public function postData(): array { return $this->post; }
    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') !== '' && ($this->server['HTTPS'] ?? '') !== 'off';
    }
}
