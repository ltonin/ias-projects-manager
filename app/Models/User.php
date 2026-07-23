<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PARTICIPANT = 'participant';
    public const ROLE_VIEWER = 'viewer';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_PARTICIPANT, self::ROLE_VIEWER];

    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $role,
        public readonly bool $isActive,
        public readonly ?DateTimeImmutable $lastLoginAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function fullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
