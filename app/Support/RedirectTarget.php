<?php

declare(strict_types=1);

namespace App\Support;

final class RedirectTarget
{
    public static function sanitize(mixed $target, string $fallback = '/'): string
    {
        if (!is_string($target) || $target === '' || !str_starts_with($target, '/')) {
            return $fallback;
        }
        if (str_starts_with($target, '//') || str_contains($target, '\\') || preg_match('/[\r\n]/', $target) === 1) {
            return $fallback;
        }
        $parts = parse_url($target);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return $fallback;
        }
        return $target;
    }
}
