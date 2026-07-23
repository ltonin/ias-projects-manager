<?php
declare(strict_types=1);
namespace App\Models;

final class AnnualEffortPage
{
    /**
     * @param list<array<string,mixed>> $sections
     * @param array<int,string> $projectMonthlyHours
     * @param list<array<string,mixed>> $capacity
     * @param array{count:int,planned:string,actual:string} $unassigned
     */
    public function __construct(
        public readonly Project $project,
        public readonly int $year,
        public readonly bool $canManage,
        public readonly array $sections,
        public readonly array $projectMonthlyHours,
        public readonly string $projectAnnualHours,
        public readonly int $workPackagesWithEffort,
        public readonly int $participantsWithEffort,
        public readonly array $capacity,
        public readonly array $unassigned,
        public readonly string $concurrencyToken,
        public readonly int $divergentCount,
        public readonly ?int $currentMonth,
    ) {}
}
