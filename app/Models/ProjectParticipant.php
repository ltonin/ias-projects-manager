<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class ProjectParticipant
{
    public const ROLE_LABELS = [
        'principal_investigator' => 'Principal Investigator',
        'coordinator' => 'Project Coordinator',
        'local_coordinator' => 'Local Coordinator',
        'work_package_leader' => 'Work Package Leader',
        'task_leader' => 'Task Leader',
        'researcher' => 'Researcher',
        'postdoc' => 'Postdoctoral Researcher',
        'phd_student' => 'PhD Student',
        'research_fellow' => 'Research Fellow',
        'technician' => 'Technician',
        'administrative_support' => 'Administrative Support',
        'external_collaborator' => 'External Collaborator',
        'consultant' => 'Consultant',
        'other' => 'Other',
    ];

    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $personId,
        public readonly string $projectRole,
        public readonly ?DateTimeImmutable $participationStart,
        public readonly ?DateTimeImmutable $participationEnd,
        public readonly bool $isActive,
        public readonly ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly string $personFirstName,
        public readonly string $personLastName,
        public readonly ?string $institutionalEmail,
        public readonly ?string $affiliation,
        public readonly string $positionType,
        public readonly bool $personIsInternal,
        public readonly bool $personIsActive,
        public readonly ?DateTimeImmutable $personActiveFrom,
        public readonly ?DateTimeImmutable $personActiveTo,
        public readonly ?string $linkedUsername,
        public readonly ?bool $linkedUserIsActive,
        public readonly string $projectAcronym,
        public readonly string $projectTitle,
        public readonly string $projectStatus,
    ) {
    }

    public function personName(): string { return $this->personFirstName . ' ' . $this->personLastName; }
    public function projectName(): string { return $this->projectAcronym . ' — ' . $this->projectTitle; }
    public function roleLabel(): string { return self::ROLE_LABELS[$this->projectRole] ?? $this->projectRole; }
    public function positionLabel(): string { return Person::POSITION_LABELS[$this->positionType] ?? $this->positionType; }
    public function activeLabel(): string { return $this->isActive ? 'Active' : 'Inactive'; }
    public function period(): string
    {
        if ($this->participationStart === null && $this->participationEnd === null) return 'Not specified';
        return ($this->participationStart?->format('Y-m-d') ?? 'Open') . ' — ' . ($this->participationEnd?->format('Y-m-d') ?? 'Open');
    }
    public function withoutNotes(): self
    {
        return new self(
            $this->id, $this->projectId, $this->personId, $this->projectRole, $this->participationStart,
            $this->participationEnd, $this->isActive, null, $this->createdAt, $this->updatedAt,
            $this->personFirstName, $this->personLastName, $this->institutionalEmail, $this->affiliation,
            $this->positionType, $this->personIsInternal, $this->personIsActive, $this->personActiveFrom,
            $this->personActiveTo, $this->linkedUsername, $this->linkedUserIsActive, $this->projectAcronym,
            $this->projectTitle, $this->projectStatus,
        );
    }
}
