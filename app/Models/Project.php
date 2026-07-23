<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class Project
{
    public const STATUS_LABELS = [
        'planned' => 'Planned',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public function __construct(
        public readonly int $id,
        public readonly string $acronym,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $internalCode,
        public readonly ?string $grantAgreementNumber,
        public readonly ?string $fundingAgency,
        public readonly ?string $fundingProgramme,
        public readonly ?string $coordinatorOrganization,
        public readonly ?int $managerPersonId,
        public readonly ?DateTimeImmutable $startDate,
        public readonly ?DateTimeImmutable $endDate,
        public readonly string $status,
        public readonly ?string $totalBudget,
        public readonly ?string $currency,
        public readonly ?string $websiteUrl,
        public readonly ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?string $managerName = null,
        public readonly ?string $managerEmail = null,
    ) {
    }

    public function displayTitle(): string { return $this->acronym . ' — ' . $this->title; }
    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function period(): string
    {
        if ($this->startDate === null && $this->endDate === null) return 'Not specified';
        return ($this->startDate?->format('Y-m-d') ?? 'Open') . ' — ' . ($this->endDate?->format('Y-m-d') ?? 'Open');
    }
    public function formattedBudget(): string
    {
        return $this->totalBudget === null ? 'Not recorded' : $this->totalBudget . ' ' . $this->currency;
    }
    public function isOwnedBy(?int $personId): bool { return $personId !== null && $this->managerPersonId === $personId; }
    public function withoutNotes(): self
    {
        return new self($this->id,$this->acronym,$this->title,$this->description,$this->internalCode,$this->grantAgreementNumber,
            $this->fundingAgency,$this->fundingProgramme,$this->coordinatorOrganization,$this->managerPersonId,$this->startDate,
            $this->endDate,$this->status,$this->totalBudget,$this->currency,$this->websiteUrl,null,$this->createdAt,$this->updatedAt,
            $this->managerName,$this->managerEmail);
    }
}
