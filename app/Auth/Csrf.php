<?php

declare(strict_types=1);

namespace App\Auth;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || strlen($token) < 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }
        return $token;
    }

    public function validate(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }

    public function regenerate(): string
    {
        unset($_SESSION[self::SESSION_KEY]);
        return $this->token();
    }
}
