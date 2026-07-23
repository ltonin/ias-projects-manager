<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use DateTimeImmutable;

final class UserFactory
{
    public static function make(int $id = 1, string $role = User::ROLE_ADMIN, bool $active = true, string $email = 'user@example.test', ?string $hash = null, string $username = 'test.user'): User
    {
        $now = new DateTimeImmutable('2026-01-01 12:00:00');
        return new User($id, $username, $email, $hash ?? password_hash('correct horse battery staple', PASSWORD_DEFAULT), 'Test', 'User', $role, $active, null, $now, $now);
    }
}
