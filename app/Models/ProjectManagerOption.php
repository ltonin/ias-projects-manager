<?php

declare(strict_types=1);

namespace App\Models;

final class ProjectManagerOption
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $positionLabel,
        public readonly ?string $affiliation,
        public readonly bool $isActive,
        public readonly ?string $username,
    ) {}
    public function label(): string
    {
        return implode(' — ', array_filter([
            $this->name, $this->positionLabel, $this->affiliation,
            $this->isActive ? 'active' : 'inactive', $this->username,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
