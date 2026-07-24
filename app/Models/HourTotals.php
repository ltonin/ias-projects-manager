<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PersonMonthConverter;

final class HourTotals
{
    public function __construct(
        public readonly string $plannedHours,
        public readonly string $actualHours,
        public readonly int $allocationCount,
        public readonly int $participantCount = 0,
        public readonly int $distinctMonthCount = 0,
        public readonly int $distinctProjectMonthCount = 0,
    ) {
    }
    public function allocatedHours():string{return$this->actualHours!=='0.00'?$this->actualHours:$this->plannedHours;}
    public function variance(PersonMonthConverter $converter): string { return $converter->subtract($this->actualHours, $this->plannedHours) ?? '0.00'; }
}
