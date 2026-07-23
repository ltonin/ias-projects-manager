<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PROJECT_MANAGER = 'project_manager';
    public const ROLE_PARTICIPANT = 'participant';
    public const ROLE_VIEWER = 'viewer';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_PROJECT_MANAGER, self::ROLE_PARTICIPANT, self::ROLE_VIEWER];
    public const MANAGEABLE_ROLES = [self::ROLE_PROJECT_MANAGER, self::ROLE_PARTICIPANT, self::ROLE_VIEWER];
    public const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_PROJECT_MANAGER => 'Project Manager',
        self::ROLE_PARTICIPANT => 'Participant',
        self::ROLE_VIEWER => 'Viewer',
    ];

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

    public function isProjectManager(): bool { return $this->role === self::ROLE_PROJECT_MANAGER; }
    public function roleLabel(): string { return self::ROLE_LABELS[$this->role] ?? $this->role; }
}
