<?php

declare(strict_types=1);

namespace App\Routing;

use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use Closure;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @param callable(array<string, string>): mixed $handler */
    public function get(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('GET', $path, $handler, $name);
    }

    /** @param callable(array<string, string>): mixed $handler */
    public function post(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('POST', $path, $handler, $name);
    }

    /** @param callable(array<string, string>): mixed $handler */
    private function add(string $method, string $path, callable $handler, ?string $name): void
    {
        $this->routes[] = new Route($method, $this->normalize($path), Closure::fromCallable($handler), $name);
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $parameters = $this->match($route->path, $request->path());
            if ($parameters === null) {
                continue;
            }
            if ($route->method !== $request->method()) {
                $allowed[] = $route->method;
                continue;
            }

            $result = ($route->handler)($parameters);
            if (!$result instanceof Response) {
                throw new \LogicException('Route handlers must return a Response.');
            }
            return $result;
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed.');
        }
        throw new HttpException(404, 'Page not found.');
    }

    /** @return array<string, string>|null */
    private function match(string $routePath, string $requestPath): ?array
    {
        $names = [];
        $quoted = preg_quote($routePath, '#');
        $pattern = preg_replace_callback('/\\\\\{([A-Za-z_][A-Za-z0-9_]*)\\\\\}/', static function (array $matches) use (&$names): string {
            $names[] = $matches[1];
            return '([^/]+)';
        }, $quoted);
        if ($pattern === null || preg_match('#^' . $pattern . '$#', $this->normalize($requestPath), $matches) !== 1) {
            return null;
        }

        array_shift($matches);
        $values = array_map('rawurldecode', $matches);
        return array_combine($names, $values) ?: [];
    }

    private function normalize(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
