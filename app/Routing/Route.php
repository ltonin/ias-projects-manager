<?php

declare(strict_types=1);

namespace App\Routing;

use Closure;

final class Route
{
    /** @param Closure(array<string, string>): mixed $handler */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly Closure $handler,
        public readonly ?string $name = null,
    ) {
    }
}
