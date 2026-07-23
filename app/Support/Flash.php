<?php

declare(strict_types=1);

namespace App\Support;

final class Flash
{
    private const KEY = '_flash';

    public function add(string $type, string $message): void
    {
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public function consume(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return is_array($messages) ? $messages : [];
    }
}
