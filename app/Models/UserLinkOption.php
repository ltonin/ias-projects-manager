<?php

declare(strict_types=1);

namespace App\Models;

final class UserLinkOption
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $isActive,
    ) {
    }

    public function label(): string
    {
        return sprintf(
            '%s — %s %s — %s — %s — %s',
            $this->username,
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->role,
            $this->isActive ? 'active' : 'inactive',
        );
    }
}
