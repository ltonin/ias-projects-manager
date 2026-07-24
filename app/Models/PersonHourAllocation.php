<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PersonMonthConverter;
use DateTimeImmutable;

final class PersonHourAllocation
{
    public const MIN_YEAR = 2000;
    public const MAX_YEAR = 2100;

    public function __construct(
        public readonly int $id,
        public readonly int $projectParticipantId,
        public readonly int $year,
        public readonly int $month,
        public readonly ?string $plannedHours,
        public readonly ?string $actualHours,
        public readonly ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly int $projectId,
        public readonly int $personId,
        public readonly string $personName,
        public readonly string $projectRole,
        public readonly string $projectAcronym,
        public readonly string $projectTitle,
        public readonly string $projectStatus,
        public readonly string $hoursPerPm,
        public readonly ?int $workPackageId = null,
        public readonly ?string $workPackageCode = null,
        public readonly ?string $workPackageTitle = null,
        public readonly ?bool $workPackageIsActive = null,
        public readonly ?DateTimeImmutable $workPackageStartDate = null,
        public readonly ?DateTimeImmutable $workPackageEndDate = null,
    ) {
    }

    public function periodKey(): string { return sprintf('%04d-%02d', $this->year, $this->month); }
    public function monthLabel(): string { return (new DateTimeImmutable($this->periodKey() . '-01'))->format('F Y'); }
    public function allocatedHours(): ?string { return $this->actualHours ?? $this->plannedHours; }
    public function allocatedPm(PersonMonthConverter $converter): ?string { return $converter->convert($this->allocatedHours(),$this->hoursPerPm); }
    public function hourVariance(PersonMonthConverter $converter): ?string { return $converter->subtract($this->actualHours, $this->plannedHours); }
    public function plannedPm(PersonMonthConverter $converter): ?string { return $converter->convert($this->plannedHours, $this->hoursPerPm); }
    public function actualPm(PersonMonthConverter $converter): ?string { return $converter->convert($this->actualHours, $this->hoursPerPm); }
    public function pmVariance(PersonMonthConverter $converter): ?string { return $converter->pmVariance($this->actualHours, $this->plannedHours, $this->hoursPerPm); }
    public function workPackageLabel(): string { return $this->workPackageId===null?'Unassigned':$this->workPackageCode.' — '.$this->workPackageTitle; }
    /** @return list<string> */
    public function warnings():array{return$this->workPackageIsActive===false?['The associated Work Package is inactive.']:[];}
    public function withoutNotes(): self
    {
        return new self($this->id,$this->projectParticipantId,$this->year,$this->month,$this->plannedHours,$this->actualHours,null,
            $this->createdAt,$this->updatedAt,$this->projectId,$this->personId,$this->personName,$this->projectRole,
            $this->projectAcronym,$this->projectTitle,$this->projectStatus,$this->hoursPerPm,$this->workPackageId,
            $this->workPackageCode,$this->workPackageTitle,$this->workPackageIsActive,$this->workPackageStartDate,$this->workPackageEndDate);
    }
}
