<?php
declare(strict_types=1);
namespace App\Models;

use DateTimeImmutable;

final class WorkPackage
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly string $code,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?DateTimeImmutable $startDate,
        public readonly ?DateTimeImmutable $endDate,
        public readonly ?int $responsibleParticipantId,
        public readonly bool $isActive,
        public readonly ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly string $projectAcronym,
        public readonly string $projectTitle,
        public readonly ?string $responsibleFirstName = null,
        public readonly ?string $responsibleLastName = null,
        public readonly ?string $responsibleRole = null,
        public readonly ?bool $responsibleParticipantActive = null,
        public readonly ?bool $responsiblePersonActive = null,
        public readonly ?bool $responsibleUserActive = null,
    ) {}

    public function displayTitle(): string { return $this->code . ' — ' . $this->title; }
    public function projectName(): string { return $this->projectAcronym . ' — ' . $this->projectTitle; }
    public function period(): string
    {
        if ($this->startDate === null && $this->endDate === null) return 'Not specified';
        return ($this->startDate?->format('Y-m-d') ?? 'Open') . ' — ' . ($this->endDate?->format('Y-m-d') ?? 'Open');
    }
    public function responsibleName(): string
    {
        return $this->responsibleParticipantId === null
            ? 'No responsible participant'
            : trim((string) $this->responsibleFirstName . ' ' . (string) $this->responsibleLastName);
    }
    public function responsibleRoleLabel(): ?string
    {
        return $this->responsibleRole === null ? null : (ProjectParticipant::ROLE_LABELS[$this->responsibleRole] ?? $this->responsibleRole);
    }
    /** @return list<string> */
    public function warnings(): array
    {
        $warnings = [];
        if ($this->responsibleParticipantActive === false) $warnings[] = 'The responsible participation is inactive.';
        if ($this->responsiblePersonActive === false) $warnings[] = 'The responsible person is inactive.';
        if ($this->responsibleUserActive === false) $warnings[] = 'The responsible person’s linked account is inactive.';
        return $warnings;
    }
    public function withoutNotes(): self
    {
        return new self($this->id,$this->projectId,$this->code,$this->title,$this->description,$this->startDate,$this->endDate,
            $this->responsibleParticipantId,$this->isActive,null,$this->createdAt,$this->updatedAt,$this->projectAcronym,
            $this->projectTitle,$this->responsibleFirstName,$this->responsibleLastName,$this->responsibleRole,
            $this->responsibleParticipantActive,$this->responsiblePersonActive,$this->responsibleUserActive);
    }
}
