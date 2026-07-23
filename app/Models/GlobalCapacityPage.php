<?php
declare(strict_types=1);
namespace App\Models;

final class GlobalCapacityPage
{
    /** @param list<array{person:Person,months:array,annualCapacity:string,overrideCount:int}> $people */
    public function __construct(
        public readonly int $year,
        public readonly array $people,
        public readonly bool $defaultExpanded,
        public readonly bool $canEdit,
    ){}
}
