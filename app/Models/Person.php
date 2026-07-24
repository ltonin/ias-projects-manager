<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class Person
{
    public const POSITION_LABELS = [
        'full_professor' => 'Full Professor',
        'associate_professor' => 'Associate Professor',
        'assistant_professor' => 'Assistant Professor',
        'researcher' => 'Researcher',
        'postdoc' => 'Postdoctoral Researcher',
        'phd_student' => 'PhD Student',
        'research_fellow' => 'Research Fellow',
        'technician' => 'Technician',
        'administrative_staff' => 'Administrative Staff',
        'external_collaborator' => 'External Collaborator',
        'other' => 'Other',
    ];

    public function __construct(
        public readonly int $id,
        public readonly ?int $userId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $institutionalEmail,
        public readonly ?string $affiliation,
        public readonly string $positionType,
        public readonly bool $isInternal,
        public readonly ?DateTimeImmutable $activeFrom,
        public readonly ?DateTimeImmutable $activeTo,
        public readonly bool $isActive,
        public readonly ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?string $linkedUsername = null,
        public readonly string $defaultMonthlyCapacityHours = '125.00',
        public readonly string $annualCapacityHours = '1500.00',
    ) {
    }

    public static function defaultAnnualCapacity(string $positionType): string
    {
        return in_array($positionType, ['full_professor','associate_professor','assistant_professor','researcher'], true)
            ? '1150.00' : '1500.00';
    }

    public function fullName(): string { return $this->firstName . ' ' . $this->lastName; }
    public function positionLabel(): string { return self::POSITION_LABELS[$this->positionType] ?? $this->positionType; }
    public function internalLabel(): string { return $this->isInternal ? 'Internal' : 'External'; }
    public function activeLabel(): string { return $this->isActive ? 'Active' : 'Inactive'; }

    public function associationPeriod(): string
    {
        if ($this->activeFrom === null && $this->activeTo === null) {
            return 'Not specified';
        }
        return ($this->activeFrom?->format('Y-m-d') ?? 'Open') . ' — ' . ($this->activeTo?->format('Y-m-d') ?? 'Open');
    }
}
