<?php

declare(strict_types=1);

namespace App\Models;

final class ParticipantPersonOption
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $positionLabel,
        public readonly ?string $affiliation,
        public readonly ?string $institutionalEmail,
        public readonly bool $isActive,
        public readonly ?string $username,
        public readonly ?string $activeFrom,
        public readonly ?string $activeTo,
    ) {
    }

    public function label(): string
    {
        return implode(' — ', array_filter([
            $this->name,
            $this->positionLabel,
            $this->affiliation,
            $this->institutionalEmail,
            $this->isActive ? 'active' : 'inactive',
            $this->username,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
