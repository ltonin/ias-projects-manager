<?php
declare(strict_types=1);
namespace App\Models;

final class GlobalAnnualOverviewPage
{
    /** @param list<array<string,mixed>> $projects */
    public function __construct(
        public readonly int $year,
        public readonly array $projects,
        public readonly ?int $currentMonth,
    ){}
}
